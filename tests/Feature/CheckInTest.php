<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserCheckIn;
use App\Models\UserCheckInStat;
use App\Models\Wallet;
use App\Services\CheckInConfigService;
use App\Services\CheckInService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected CheckInService $checkInService;
    protected CheckInConfigService $configService;

    protected function setUp(): void
    {
        parent::setUp();

        // 清空 Redis 缓存并同步最新配置，隔离测试环境[cite: 10]
        Redis::flushdb();

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        Wallet::firstOrCreate(['user_id' => $this->user->id], ['coins' => 0]);

        $this->configService = app(CheckInConfigService::class);
        $this->configService->syncFromConfigFile();
        $this->checkInService = app(CheckInService::class);

        // 锁定测试基准时间：2026年8月15日
        Carbon::setTestNow(Carbon::parse('2026-08-15 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Redis::flushdb();
        parent::tearDown();
    }

    /**
     * 测试 1：今日首次打卡成功
     */
    public function test_user_can_check_in_today_successfully(): void
    {
        $result = $this->checkInService->execute($this->user);

        $this->assertEquals(1, $result['continuous_days']);
        $this->assertEquals(1, $result['total_days']);
        $this->assertEquals(10, $result['base_coins']);
        $this->assertEquals(0, $result['bonus_coins']);
        $this->assertEquals(10, $result['total_earned_coins']);

        $this->assertDatabaseHas('user_check_ins', [
            'user_id' => $this->user->id,
            'check_in_date' => '2026-08-15',
            'type' => 'normal',
        ]);
    }

    /**
     * 测试 2：同日防重复打卡
     */
    public function test_user_cannot_check_in_twice_in_the_same_day(): void
    {
        $this->checkInService->execute($this->user);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/今日已打卡/u');

        $this->checkInService->execute($this->user);
    }

    /**
     * 测试 3：不能在未来的日期打卡
     */
    public function test_user_cannot_check_in_future_date(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/不能在未来的日期打卡/u');

        $this->checkInService->execute($this->user, '2026-08-16');
    }

    /**
     * 测试 4：连续签到 3 天触发阶梯里程碑
     */
    public function test_consecutive_three_days_streak_triggers_milestone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 10:00:00'));
        $this->checkInService->execute($this->user);

        Carbon::setTestNow(Carbon::parse('2026-08-14 10:00:00'));
        $this->checkInService->execute($this->user);

        Carbon::setTestNow(Carbon::parse('2026-08-15 10:00:00'));
        $result = $this->checkInService->execute($this->user);

        $this->assertEquals(3, $result['continuous_days']);
        $this->assertEquals(20, $result['bonus_coins']);
        $this->assertEquals(30, $result['total_earned_coins']);
        $this->assertEquals(50, $this->user->wallet->fresh()->coins);
    }

    /**
     * 测试 5：补签修复断签天数并消耗补签卡
     */
    public function test_make_up_check_in_heals_streak_and_consumes_card(): void
    {
        UserCheckInStat::create([
            'user_id' => $this->user->id,
            'last_check_in_date' => '2026-08-15',
            'continuous_days' => 1,
            'total_days' => 2,
            'make_up_cards' => 1,
            'total_make_up_cards_earned' => 1,
            'month_make_up_count' => 0,
        ]);

        UserCheckIn::create([
            'user_id' => $this->user->id,
            'check_in_date' => '2026-08-13',
            'type' => 'normal',
            'continuous_days_snapshot' => 1,
            'total_days_snapshot' => 1,
            'reward_coins' => 10,
        ]);
        UserCheckIn::create([
            'user_id' => $this->user->id,
            'check_in_date' => '2026-08-15',
            'type' => 'normal',
            'continuous_days_snapshot' => 1,
            'total_days_snapshot' => 2,
            'reward_coins' => 10,
        ]);

        $result = $this->checkInService->execute($this->user, '2026-08-14');

        $this->assertEquals(3, $result['continuous_days']);
        $this->assertEquals(20, $result['bonus_coins']);
        $this->assertEquals(0, $result['current_cards']);
    }

    /**
     * 测试 6：没有补签卡时补签被拦截
     */
    public function test_make_up_check_in_fails_when_no_cards_available(): void
    {
        UserCheckInStat::create([
            'user_id' => $this->user->id,
            'last_check_in_date' => '2026-08-15',
            'make_up_cards' => 0,
            'month_make_up_count' => 0,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/当前持有的补签卡数量不足/u');

        $this->checkInService->execute($this->user, '2026-08-14');
    }

    /**
     * 测试 7：当月补签次数达到上限时被拦截（动态绑定上限值）
     */
    public function test_make_up_check_in_fails_when_exceeding_monthly_quota(): void
    {
        $makeUpConfig = $this->configService->getMakeUpConfig();
        $maxPerMonth = (int) ($makeUpConfig['max_per_month'] ?? 3);

        UserCheckInStat::create([
            'user_id' => $this->user->id,
            'last_check_in_date' => '2026-08-15',
            'make_up_cards' => 5,
            'month_make_up_count' => $maxPerMonth,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/本月补签次数已达上限/u');

        $this->checkInService->execute($this->user, '2026-08-14');
    }

    /**
     * 测试 8：严格限制只能补签当月
     */
    public function test_user_cannot_make_up_check_in_for_previous_month(): void
    {
        UserCheckInStat::create([
            'user_id' => $this->user->id,
            'last_check_in_date' => '2026-08-15',
            'make_up_cards' => 2,
            'month_make_up_count' => 0,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/补签仅限补签当月内的漏签日期/u');

        $this->checkInService->execute($this->user, '2026-07-31');
    }

    /**
     * 测试 9：同一个月内相同里程碑不可重复领取
     */
    public function test_milestone_cannot_be_awarded_twice_in_the_same_month(): void
    {
        UserCheckIn::create([
            'user_id' => $this->user->id,
            'check_in_date' => '2026-08-01',
            'type' => 'normal',
            'continuous_days_snapshot' => 1,
            'total_days_snapshot' => 1,
            'reward_coins' => 10,
        ]);
        UserCheckIn::create([
            'user_id' => $this->user->id,
            'check_in_date' => '2026-08-02',
            'type' => 'normal',
            'continuous_days_snapshot' => 2,
            'total_days_snapshot' => 2,
            'reward_coins' => 10,
        ]);
        UserCheckIn::create([
            'user_id' => $this->user->id,
            'check_in_date' => '2026-08-03',
            'type' => 'normal',
            'continuous_days_snapshot' => 3,
            'total_days_snapshot' => 3,
            'reward_coins' => 10,
            'bonus_coins' => 20,
        ]);

        UserCheckIn::create([
            'user_id' => $this->user->id,
            'check_in_date' => '2026-08-13',
            'type' => 'normal',
            'continuous_days_snapshot' => 1,
            'total_days_snapshot' => 4,
            'reward_coins' => 10,
        ]);
        UserCheckIn::create([
            'user_id' => $this->user->id,
            'check_in_date' => '2026-08-14',
            'type' => 'normal',
            'continuous_days_snapshot' => 2,
            'total_days_snapshot' => 5,
            'reward_coins' => 10,
        ]);

        $result = $this->checkInService->execute($this->user);

        $this->assertEquals(3, $result['continuous_days']);
        $this->assertEquals(0, $result['bonus_coins']);
        $this->assertEquals(10, $result['total_earned_coins']);
    }

    /**
     * 测试 10：满月全勤动态天数适配 (8月份 31 天)
     */
    public function test_full_month_streak_awards_king_milestone_on_day_31(): void
    {
        for ($day = 1; $day <= 30; $day++) {
            $dStr = sprintf('2026-08-%02d', $day);
            UserCheckIn::create([
                'user_id' => $this->user->id,
                'check_in_date' => $dStr,
                'type' => 'normal',
                'continuous_days_snapshot' => $day,
                'total_days_snapshot' => $day,
                'reward_coins' => 10,
            ]);
        }

        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00'));

        $result = $this->checkInService->execute($this->user);

        $fullMonthConfig = $this->configService->getMilestonesForMonth('2026-08')[31];
        $expectedCards = (int) ($fullMonthConfig['cards'] ?? 3);

        $this->assertEquals(31, $result['continuous_days']);
        $this->assertEquals(300, $result['bonus_coins']);
        $this->assertEquals($expectedCards, $result['earned_cards']);
        $this->assertEquals(310, $result['total_earned_coins']);
    }

    /**
     * 测试 11：HTTP POST /checkin 接口返回规范 JSON
     */
    public function test_http_api_check_in_returns_json_successfully(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/checkin');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '打卡成功！',
                'data' => [
                    'continuous_days' => 1,
                    'total_days' => 1,
                    'total_earned_coins' => 10,
                    'signed_date' => '2026-08-15',
                    'is_make_up' => false,
                ],
            ]);
    }
}
