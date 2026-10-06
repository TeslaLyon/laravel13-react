<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ChannelSubscriptionService
{
    /**
     * Redis 片商订阅量累加缓冲 Hash Key
     */
    public const BUFFER_KEY = 'channel:subscribers:buffer';

    /**
     * 最优最高效读取用户对片商的订阅状态及通知偏好（单条索引查询，覆盖快捷订阅与偏好订阅）
     *
     * @return array{is_subscribed: bool, notification_type: string}
     */
    public function getSubscriptionStatus(Channel $channel, ?User $user): array
    {
        if (!$user) {
            return [
                'is_subscribed' => false,
                'notification_type' => 'personalized',
            ];
        }

        if ($channel->isNotRegisteredAsLoveReactant()) {
            $channel->registerAsLoveReactant();
        }

        if ($user->isNotRegisteredAsLoveReacter()) {
            $user->registerAsLoveReacter();
        }

        $reacterId = $user->love_reacter_id;
        $reactantId = $channel->love_reactant_id;

        if (!$reacterId || !$reactantId) {
            return [
                'is_subscribed' => false,
                'notification_type' => 'personalized',
            ];
        }

        $reactionTypeName = DB::table('love_reactions as r')
            ->join('love_reaction_types as rt', 'rt.id', '=', 'r.reaction_type_id')
            ->where('r.reacter_id', $reacterId)
            ->where('r.reactant_id', $reactantId)
            ->whereIn('rt.name', [
                'SubscribeChannel',
                'SubscribePersonalized',
                'SubscribeAll',
                'SubscribeNone',
            ])
            ->value('rt.name');

        if (!$reactionTypeName) {
            return [
                'is_subscribed' => false,
                'notification_type' => 'personalized',
            ];
        }

        return [
            'is_subscribed' => true,
            'notification_type' => match ($reactionTypeName) {
                'SubscribeAll' => 'all',
                'SubscribeNone' => 'none',
                default => 'personalized',
            },
        ];
    }

    /**
     * 处理用户对片商的“订阅 / 取消订阅”切换逻辑
     *
     * @return array{is_subscribed: bool, message: string, subscribers_count: int}
     */
    public function toggleSubscribe(Channel $channel, User $user): array
    {
        if ($channel->isNotRegisteredAsLoveReactant()) {
            $channel->registerAsLoveReactant();
        }

        if ($user->isNotRegisteredAsLoveReacter()) {
            $user->registerAsLoveReacter();
        }

        $reacter = $user->viaLoveReacter();

        if ($reacter->hasReactedTo($channel, 'SubscribeChannel')) {
            $reacter->unreactTo($channel, 'SubscribeChannel');
            $this->incrementBuffer($channel->id, -1);

            return [
                'is_subscribed' => false,
                'message' => '已取消订阅',
                'subscribers_count' => $this->getSubscribersCount($channel),
            ];
        }

        $reacter->reactTo($channel, 'SubscribeChannel');
        $this->incrementBuffer($channel->id, 1);

        return [
            'is_subscribed' => true,
            'message' => '订阅成功',
            'subscribers_count' => $this->getSubscribersCount($channel),
        ];
    }

    /**
     * 变更片商订阅量缓冲（Redis HINCRBY 原子操作）
     *
     * @param int $channelId 片商ID
     * @param int $delta 变动量 (+1 或 -1)
     */
    public function incrementBuffer(int $channelId, int $delta): void
    {
        try {
            Redis::hincrby(self::BUFFER_KEY, (string) $channelId, $delta);
        } catch (\Throwable $e) {
            Log::warning("Redis unavailable when buffering channel subscriber delta for channel {$channelId}: " . $e->getMessage());

            // Redis 故障降级直接更新数据库 channels 表
            $channel = Channel::find($channelId);
            if ($channel) {
                if ($delta > 0) {
                    $channel->increment('follow_num', $delta);
                } elseif ($delta < 0) {
                    $channel->where('id', $channelId)
                        ->where('follow_num', '>', 0)
                        ->decrement('follow_num', abs($delta));
                }
            }
        }
    }

    /**
     * 获取片商的实时订阅人数（数据库基数 + Redis 缓冲区增量）
     */
    public function getSubscribersCount(Channel $channel): int
    {
        $bufferedDelta = 0;

        try {
            $buffered = Redis::hget(self::BUFFER_KEY, (string) $channel->id);
            if ($buffered !== null && $buffered !== false) {
                $bufferedDelta = (int) $buffered;
            }
        } catch (\Throwable $e) {
            Log::warning("Redis unavailable when getting buffered subscribers for channel {$channel->id}: " . $e->getMessage());
        }

        $baseCount = (int) ($channel->follow_num ?? 0);

        return max(0, $baseCount + $bufferedDelta);
    }

    /**
     * 将 Redis 缓冲区的增量原子化同步至数据库 channels 表
     *
     * 采用 RENAME 零锁快照模式，保证高并发下增量统计绝不丢失
     *
     * @return int 处理的片商数量
     */
    public function syncBufferToDatabase(): int
    {
        $snapshotKey = self::BUFFER_KEY . ':snapshot:' . uniqid('', true);

        try {
            // 1. 原子重命名缓冲 key 为快照 key
            Redis::rename(self::BUFFER_KEY, $snapshotKey);
        } catch (\Throwable $e) {
            // 如果 key 不存在或无更新，RENAME 会报错，属于正常空载状态
            return 0;
        }

        try {
            // 2. 读取快照中的所有变更记录
            /** @var array<string, string> $deltas */
            $deltas = Redis::hgetall($snapshotKey);

            if (empty($deltas)) {
                Redis::del($snapshotKey);
                return 0;
            }

            // 3. 批量更新数据库 channels 表
            DB::transaction(function () use ($deltas) {
                foreach ($deltas as $channelIdStr => $deltaStr) {
                    $channelId = (int) $channelIdStr;
                    $delta = (int) $deltaStr;

                    if ($delta === 0) {
                        continue;
                    }

                    if ($delta > 0) {
                        DB::table('channels')
                            ->where('id', $channelId)
                            ->increment('follow_num', $delta);
                    } else {
                        // 递减时避免数值降为负数
                        DB::table('channels')
                            ->where('id', $channelId)
                            ->where('follow_num', '>=', abs($delta))
                            ->decrement('follow_num', abs($delta));

                        // 保底纠偏：若现有 follow_num 小于减量，归零
                        DB::table('channels')
                            ->where('id', $channelId)
                            ->where('follow_num', '<', abs($delta))
                            ->update(['follow_num' => 0]);
                    }
                }
            });

            // 4. 清理快照 key
            Redis::del($snapshotKey);

            return count($deltas);
        } catch (\Throwable $e) {
            Log::error('Error syncing channel subscribers buffer to database: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            // 发生异常时将未持久化的快照数据回滚合并回原缓冲 Hash
            $this->mergeSnapshotBack($snapshotKey);

            throw $e;
        }
    }

    /**
     * 全量校准同步：从 love_reactions 事实表重新统计所有片商的真实订阅数并同步至 channels 表
     *
     * 涵盖 SubscribeChannel 以及所有个性化偏好订阅（SubscribeAll, SubscribePersonalized, SubscribeNone）
     */
    public function syncAllCounts(): int
    {
        // 0. 自动扫描并为所有缺失 reactant 的片商补齐注册
        Channel::query()
            ->whereNull('love_reactant_id')
            ->chunkById(200, function ($channels) {
                foreach ($channels as $channel) {
                    $channel->registerAsLoveReactant();
                }
            });

        $reactionTypes = [
            'SubscribeChannel',
            'SubscribeAll',
            'SubscribePersonalized',
            'SubscribeNone',
        ];

        // 1. 查找各反应类型的 ID 集合
        $reactionTypeIds = DB::table('love_reaction_types')
            ->whereIn('name', $reactionTypes)
            ->pluck('id');

        if ($reactionTypeIds->isEmpty()) {
            return 0;
        }

        // 2. 获取 Channel 对应的 reactant type 标识
        $channelClass = (new Channel)->getMorphClass();

        // 3. 聚合统计事实表：按 reactant_id 分组统计独立的 reacter 数量
        $actualCounts = DB::table('love_reactions as r')
            ->join('love_reactants as t', 't.id', '=', 'r.reactant_id')
            ->where('t.type', $channelClass)
            ->whereIn('r.reaction_type_id', $reactionTypeIds)
            ->groupBy('r.reactant_id')
            ->select('r.reactant_id', DB::raw('count(distinct r.reacter_id) as total'))
            ->pluck('total', 'reactant_id');

        $updatedCount = 0;

        Channel::query()
            ->whereNotNull('love_reactant_id')
            ->select(['id', 'love_reactant_id', 'follow_num'])
            ->chunkById(200, function ($channels) use ($actualCounts, &$updatedCount) {
                foreach ($channels as $channel) {
                    $realCount = (int) ($actualCounts[$channel->love_reactant_id] ?? 0);
                    if ((int) $channel->follow_num !== $realCount) {
                        $channel->update(['follow_num' => $realCount]);
                        $updatedCount++;
                    }
                }
            });

        // 4. 清理残留的 Redis 增量缓冲，避免校准后叠加重复数据
        try {
            Redis::del(self::BUFFER_KEY);
        } catch (\Throwable $e) {
            Log::warning('Failed to clear channel subscriber buffer during full reconciliation: ' . $e->getMessage());
        }

        return $updatedCount;
    }

    /**
     * 快照恢复兜底逻辑
     */
    protected function mergeSnapshotBack(string $snapshotKey): void
    {
        try {
            $deltas = Redis::hgetall($snapshotKey);
            if (!empty($deltas)) {
                foreach ($deltas as $channelId => $delta) {
                    Redis::hincrby(self::BUFFER_KEY, (string) $channelId, (int) $delta);
                }
            }
            Redis::del($snapshotKey);
        } catch (\Throwable $e) {
            Log::critical("Failed to merge back snapshot {$snapshotKey}: " . $e->getMessage());
        }
    }
}
