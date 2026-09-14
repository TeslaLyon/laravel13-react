<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserCheckIn;
use App\Models\UserCheckInStat;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CheckInService
{
    public function __construct(
        protected CheckInConfigService $configService,
        protected WalletService $walletService
    ) {
    }

    /**
     * 执行打卡 / 补签（自然月周期机制）
     *
     * @param User $user 当前打卡用户
     * @param string|null $dateStr 目标日期 (YYYY-MM-DD)，为 null 时代表今日打卡
     * @return array 打卡结果汇总
     * @throws RuntimeException
     */
    public function execute(User $user, ?string $dateStr = null): array
    {
        $today = Carbon::today();
        $targetDate = $dateStr ? Carbon::parse($dateStr)->startOfDay() : $today;
        $isMakeUp = $targetDate->lt($today);

        // 1. 基础时序合法性校验
        if ($targetDate->gt($today)) {
            throw new RuntimeException('不能在未来的日期打卡。');
        }

        $makeUpConfig = $this->configService->getMakeUpConfig();
        if ($isMakeUp && !($makeUpConfig['enabled'] ?? true)) {
            throw new RuntimeException('补签功能暂未开启。');
        }

        // 2. 补签专属规则校验
        if ($isMakeUp) {
            // 严格限制只能补签【当月】的漏签日期
            if (!$targetDate->isSameMonth($today)) {
                throw new RuntimeException('补签仅限补签当月内的漏签日期，跨月历史日期无法补签。');
            }

            // 补签时间窗口限制（近 30 天）
            $maxDaysLimit = (int) ($makeUpConfig['max_days_limit'] ?? 30);
            $earliestAllowedDate = $today->copy()->subDays($maxDaysLimit)->startOfDay();
            if ($targetDate->lt($earliestAllowedDate)) {
                throw new RuntimeException("只能补签近 {$maxDaysLimit} 天以内的打卡。");
            }
        }

        // 3. 分布式并发锁
        $lockKey = "checkin:lock:user:{$user->id}";
        $lock = Cache::lock($lockKey, 5);

        return $lock->get(function () use ($user, $targetDate, $today, $isMakeUp, $makeUpConfig) {
            return DB::transaction(function () use ($user, $targetDate, $today, $isMakeUp, $makeUpConfig) {

                $initialGiftCards = (int) ($makeUpConfig['initial_gift_cards'] ?? 1);
                $maxPerMonth = (int) ($makeUpConfig['max_per_month'] ?? 3);

                /** @var UserCheckInStat $stats */
                $stats = UserCheckInStat::query()->lockForUpdate()->firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'last_check_in_date' => null,
                        'continuous_days' => 0,
                        'longest_continuous_days' => 0,
                        'total_days' => 0,
                        'make_up_cards' => $initialGiftCards,
                        'total_make_up_cards_earned' => $initialGiftCards,
                        'month_make_up_count' => 0,
                    ]
                );

                // 跨月安全重置：只有当存在上次记录且跨月时才清零
                $targetMonthStr = $targetDate->format('Y-m');
                $lastCheckMonthStr = $stats->last_check_in_date ? Carbon::parse($stats->last_check_in_date)->format('Y-m') : null;
                if ($lastCheckMonthStr && $lastCheckMonthStr !== $targetMonthStr) {
                    $stats->month_make_up_count = 0;
                }

                // 补签资格判定
                if ($isMakeUp) {
                    if ($stats->month_make_up_count >= $maxPerMonth) {
                        throw new RuntimeException("本月补签次数已达上限 ({$maxPerMonth}次)。");
                    }
                    if ($stats->make_up_cards < 1) {
                        throw new RuntimeException('当前持有的补签卡数量不足。');
                    }
                }

                // 4. 防重复打卡
                $alreadyChecked = UserCheckIn::where('user_id', $user->id)
                    ->where('check_in_date', $targetDate->toDateString())
                    ->exists();

                if ($alreadyChecked) {
                    throw new RuntimeException($isMakeUp ? '该日期已经签到。' : '今日已打卡。');
                }

                // 5. 扣除补签卡（模型内已自动累加 month_make_up_count 并写流水）
                if ($isMakeUp) {
                    $stats->consumeCard(
                        action: 'make_up_checkin',
                        description: "补签打卡 {$targetDate->toDateString()}"
                    );
                }

                // 6. 写入打卡记录
                $baseCoins = $this->configService->getBaseCoins();
                $checkInRecord = UserCheckIn::create([
                    'user_id' => $user->id,
                    'check_in_date' => $targetDate->toDateString(),
                    'type' => $isMakeUp ? 'make_up' : 'normal',
                    'continuous_days_snapshot' => 1,
                    'total_days_snapshot' => $stats->total_days + 1,
                    'reward_coins' => $baseCoins,
                    'bonus_coins' => 0,
                    'extra_rewards' => null,
                ]);

                // 按当月范围拓扑计算连续签到天数
                $newContinuousDays = $this->calculateMonthContinuousStreak($user->id, $targetDate);
                $newTotalDays = $stats->total_days + 1;
                $longestDays = max($stats->longest_continuous_days, $newContinuousDays);

                // 动态获取当月里程碑
                $milestones = $this->configService->getMilestonesForMonth($targetDate);
                $bonusCoins = 0;
                $earnedCards = 0;
                $reachedMilestone = null;

                if (isset($milestones[$newContinuousDays])) {
                    $candidate = $milestones[$newContinuousDays];

                    $alreadyAwardedThisMonth = $this->checkMilestoneAwardedInMonth(
                        userId: $user->id,
                        milestoneDays: $newContinuousDays,
                        yearMonth: $targetMonthStr
                    );

                    if (!$alreadyAwardedThisMonth) {
                        $bonusCoins = (int) ($candidate['coins'] ?? 0);
                        $earnedCards = (int) ($candidate['cards'] ?? 0);
                        $reachedMilestone = $candidate;
                    }
                }

                $totalCoins = $baseCoins + $bonusCoins;

                // 7. 更新打卡流水快照
                $checkInRecord->update([
                    'continuous_days_snapshot' => $newContinuousDays,
                    'total_days_snapshot' => $newTotalDays,
                    'bonus_coins' => $bonusCoins,
                    'extra_rewards' => array_merge($reachedMilestone ?? [], [
                        'earned_cards' => $earnedCards,
                    ]),
                ]);

                // 8. 里程碑赠卡处理
                if ($earnedCards > 0) {
                    $stats->addCards(
                        count: $earnedCards,
                        action: 'milestone_reward',
                        description: "达成当月连签 {$newContinuousDays} 天里程碑赠送",
                        source: $checkInRecord
                    );
                }

                // 9. 更新统计主表
                $latestSignedDate = UserCheckIn::where('user_id', $user->id)->max('check_in_date');
                $stats->last_check_in_date = Carbon::parse($latestSignedDate)->toDateString();
                $stats->continuous_days = $newContinuousDays;
                $stats->longest_continuous_days = $longestDays;
                $stats->total_days = $newTotalDays;
                $stats->save();

                // 10. 钱包加金币
                /** @var Wallet $wallet */
                // 🌟 使用 getOrCreateWallet 安全获取或初始化带防篡改签名的钱包[cite: 4]
                $wallet = $this->walletService->getOrCreateWallet($user);

                // 🌟 调用 WalletService 的 changeCoins 进行安全加币并写入审计流水[cite: 4]
                $this->walletService->changeCoins(
                    wallet: $wallet,
                    amount: $totalCoins,
                    type: $isMakeUp ? 'make_up_reward' : 'check_in',
                    description: ($isMakeUp ? '补签打卡奖励' : '每日签到打卡') . " (当月连签第 {$newContinuousDays} 天" . ($bonusCoins > 0 ? "，含里程碑 +{$bonusCoins}" : "") . ")",
                    source: $checkInRecord,
                    metadata: [
                        'check_in_id'      => $checkInRecord->id,
                        'check_in_date'    => $targetDate->toDateString(),
                        'continuous_days'  => $newContinuousDays,
                        'base_coins'       => $baseCoins,
                        'bonus_coins'      => $bonusCoins,
                        'is_make_up'       => $isMakeUp,
                    ]
                );

                return [
                    'continuous_days' => $newContinuousDays,
                    'total_days' => $newTotalDays,
                    'base_coins' => $baseCoins,
                    'bonus_coins' => $bonusCoins,
                    'earned_cards' => $earnedCards,
                    'total_earned_coins' => $totalCoins,
                    'milestone_reached' => $reachedMilestone,
                    'current_cards' => $stats->make_up_cards,
                ];
            });
        });
    }

    /**
     * 获取月历聚合与动态策略数据
     */
    public function getMonthlyCalendarData(User $user, ?string $yearMonth = null): array
    {
        $currentMonth = $yearMonth ? Carbon::parse($yearMonth) : Carbon::today();
        $startDate = $currentMonth->copy()->startOfMonth()->toDateString();
        $endDate = $currentMonth->copy()->endOfMonth()->toDateString();

        $signedDates = UserCheckIn::query()
            ->where('user_id', $user->id)
            ->whereBetween('check_in_date', [$startDate, $endDate])
            ->pluck('check_in_date')
            ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
            ->values()
            ->toArray();

        $makeUpConfig = $this->configService->getMakeUpConfig();
        $initialGiftCards = (int) ($makeUpConfig['initial_gift_cards'] ?? 1);

        $stats = UserCheckInStat::firstOrCreate(
            ['user_id' => $user->id],
            [
                'continuous_days' => 0,
                'total_days' => 0,
                'make_up_cards' => $initialGiftCards,
                'total_make_up_cards_earned' => $initialGiftCards,
                'month_make_up_count' => 0,
            ]
        );

        $isCheckedToday = UserCheckIn::where('user_id', $user->id)
            ->where('check_in_date', Carbon::today()->toDateString())
            ->exists();

        $maxMonthMakeUp = (int) ($makeUpConfig['max_per_month'] ?? 3);
        $walletCoins = (int) ($user->wallet?->coins ?? $user->wallet?->balance ?? 0);

        $lastCheckMonthStr = $stats->last_check_in_date ? Carbon::parse($stats->last_check_in_date)->format('Y-m') : null;
        $monthUsedCount = ($lastCheckMonthStr === $currentMonth->format('Y-m')) ? $stats->month_make_up_count : 0;

        return [
            'year_month' => $currentMonth->format('Y-m'),
            'signed_dates' => $signedDates,
            'is_checked_today' => $isCheckedToday,
            'continuous_days' => $stats->continuous_days,
            'total_days' => $stats->total_days,
            'longest_continuous_days' => $stats->longest_continuous_days,
            'make_up_cards' => $stats->make_up_cards,
            'total_make_up_cards_earned' => $stats->total_make_up_cards_earned,
            'month_make_up_count' => $monthUsedCount,
            'max_month_make_up' => $maxMonthMakeUp,
            'remaining_month_make_up' => max(0, $maxMonthMakeUp - $monthUsedCount),
            'wallet_coins' => $walletCoins,
        ];
    }

    /**
     * 计算目标月份内的连续签到天数（月初 1 日自然截断）
     */
    protected function calculateMonthContinuousStreak(int $userId, Carbon $referenceDate): int
    {
        $startOfMonth = $referenceDate->copy()->startOfMonth()->toDateString();
        $endOfMonth = $referenceDate->copy()->endOfMonth()->toDateString();

        $signedDates = UserCheckIn::where('user_id', $userId)
            ->whereBetween('check_in_date', [$startOfMonth, $endOfMonth])
            ->pluck('check_in_date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        if (empty($signedDates)) {
            return 0;
        }

        $signedSet = array_flip($signedDates);
        $todayStr = Carbon::today()->toDateString();

        $cursor = null;
        if (isset($signedSet[$todayStr])) {
            $cursor = Carbon::today();
        } elseif (isset($signedSet[Carbon::today()->subDay()->toDateString()])) {
            $cursor = Carbon::today()->subDay();
        } else {
            $cursor = $referenceDate->copy();
        }

        $streak = 0;
        $monthStartCarbon = $referenceDate->copy()->startOfMonth();

        while ($cursor->gte($monthStartCarbon) && isset($signedSet[$cursor->toDateString()])) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    /**
     * 校验当月是否已发放过指定天数的里程碑奖励
     */
    protected function checkMilestoneAwardedInMonth(int $userId, int $milestoneDays, string $yearMonth): bool
    {
        $startOfMonth = Carbon::parse($yearMonth)->startOfMonth()->toDateString();
        $endOfMonth = Carbon::parse($yearMonth)->endOfMonth()->toDateString();

        return UserCheckIn::where('user_id', $userId)
            ->whereBetween('check_in_date', [$startOfMonth, $endOfMonth])
            ->where('continuous_days_snapshot', $milestoneDays)
            ->where('bonus_coins', '>', 0)
            ->exists();
    }
}
