<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class VideoInteractionService
{
    /**
     * Redis 点赞量累加缓冲 Hash Key
     */
    public const LIKES_BUFFER_KEY = 'video:likes:buffer';

    /**
     * Redis 收藏量累加缓冲 Hash Key
     */
    public const FAVORITES_BUFFER_KEY = 'video:favorites:buffer';

    /**
     * 处理用户对视频的“点赞 / 取消点赞”逻辑
     *
     * @return array{status: string, message: string, likes_count: int}
     */
    public function toggleLike(Video $video, User $user): array
    {
        $reacter = $user->viaLoveReacter();

        // 1. 互斥处理：如果用户之前踩 (Dislike) 过，先撤销踩
        if ($reacter->hasReactedTo($video, 'Dislike')) {
            $reacter->unreactTo($video, 'Dislike');
        }

        // 2. 切换处理：若已经点赞过，则取消点赞并 Redis -1
        if ($reacter->hasReactedTo($video, 'Like')) {
            $reacter->unreactTo($video, 'Like');
            $this->incrementLikesBuffer($video->id, -1);

            return [
                'status' => 'unliked',
                'message' => '已取消点赞',
                'likes_count' => $this->getLikeCount($video),
            ];
        }

        // 3. 正常点赞：新增 Like 反应并 Redis +1
        $reacter->reactTo($video, 'Like');
        $this->incrementLikesBuffer($video->id, 1);

        return [
            'status' => 'liked',
            'message' => '点赞成功',
            'likes_count' => $this->getLikeCount($video),
        ];
    }

    /**
     * 处理用户对视频的“踩 / 取消踩”逻辑
     *
     * @return array{status: string, message: string, likes_count: int}
     */
    public function toggleDislike(Video $video, User $user): array
    {
        $reacter = $user->viaLoveReacter();

        // 1. 互斥处理：如果用户之前点赞过，先撤销点赞并 Redis -1
        if ($reacter->hasReactedTo($video, 'Like')) {
            $reacter->unreactTo($video, 'Like');
            $this->incrementLikesBuffer($video->id, -1);
        }

        // 2. 切换处理：若已经踩过，则取消踩
        if ($reacter->hasReactedTo($video, 'Dislike')) {
            $reacter->unreactTo($video, 'Dislike');

            return [
                'status' => 'undisliked',
                'message' => '已取消踩',
                'likes_count' => $this->getLikeCount($video),
            ];
        }

        // 3. 正常踩：新增 Dislike 反应
        $reacter->reactTo($video, 'Dislike');

        return [
            'status' => 'disliked',
            'message' => '踩成功',
            'likes_count' => $this->getLikeCount($video),
        ];
    }

    /**
     * 处理用户对视频的“收藏 / 取消收藏”逻辑
     *
     * @return array{status: bool, message: string, favorites_count: int}
     */
    public function toggleCollect(Video $video, User $user): array
    {
        $reacter = $user->viaLoveReacter();

        // 1. 切换处理：若已经收藏过，则取消收藏并 Redis -1
        if ($reacter->hasReactedTo($video, 'VideoCollect')) {
            $reacter->unreactTo($video, 'VideoCollect');
            $this->incrementFavoritesBuffer($video->id, -1);

            return [
                'status' => false,
                'message' => '已取消收藏',
                'favorites_count' => $this->getFavoriteCount($video),
            ];
        }

        // 2. 正常收藏：新增 VideoCollect 反应并 Redis +1
        $reacter->reactTo($video, 'VideoCollect');
        $this->incrementFavoritesBuffer($video->id, 1);

        return [
            'status' => true,
            'message' => '收藏成功',
            'favorites_count' => $this->getFavoriteCount($video),
        ];
    }

    /**
     * 获取视频当前实时点赞数（数据库基数 + Redis 缓冲区增量）
     */
    public function getLikeCount(Video $video): int
    {
        $buffered = 0;
        try {
            $buffered = (int) Redis::hget(self::LIKES_BUFFER_KEY, (string) $video->id);
        } catch (\Throwable) {
        }

        return max(0, (int) $video->likes_count + $buffered);
    }

    /**
     * 获取视频当前实时收藏数（数据库基数 + Redis 缓冲区增量）
     */
    public function getFavoriteCount(Video $video): int
    {
        $buffered = 0;
        try {
            $buffered = (int) Redis::hget(self::FAVORITES_BUFFER_KEY, (string) $video->id);
        } catch (\Throwable) {
        }

        return max(0, (int) $video->favorites_count + $buffered);
    }

    /**
     * 点赞量 Redis 内存原子缓冲累加（毫秒级响应，零写锁争用）
     */
    protected function incrementLikesBuffer(int $videoId, int $delta): void
    {
        try {
            Redis::hincrby(self::LIKES_BUFFER_KEY, (string) $videoId, $delta);
        } catch (\Throwable $e) {
            Log::warning('Redis hincrby video likes failed, falling back to direct db update', [
                'video_id' => $videoId,
                'delta' => $delta,
                'error' => $e->getMessage(),
            ]);

            if ($delta > 0) {
                Video::query()->whereKey($videoId)->increment('likes_count', $delta);
            } else {
                Video::query()->whereKey($videoId)->where('likes_count', '>=', abs($delta))->decrement('likes_count', abs($delta));
            }
        }
    }

    /**
     * 收藏量 Redis 内存原子缓冲累加（毫秒级响应，零写锁争用）
     */
    protected function incrementFavoritesBuffer(int $videoId, int $delta): void
    {
        try {
            Redis::hincrby(self::FAVORITES_BUFFER_KEY, (string) $videoId, $delta);
        } catch (\Throwable $e) {
            Log::warning('Redis hincrby video favorites failed, falling back to direct db update', [
                'video_id' => $videoId,
                'delta' => $delta,
                'error' => $e->getMessage(),
            ]);

            if ($delta > 0) {
                Video::query()->whereKey($videoId)->increment('favorites_count', $delta);
            } else {
                Video::query()->whereKey($videoId)->where('favorites_count', '>=', abs($delta))->decrement('favorites_count', abs($delta));
            }
        }
    }

    /**
     * 将 Redis 缓冲区的点赞与收藏增量原子批量落库同步到数据库
     *
     * @return array{likes_synced: int, favorites_synced: int}
     */
    public function syncBufferToDatabase(): array
    {
        $syncedLikes = $this->syncKeyBuffer(self::LIKES_BUFFER_KEY, 'likes_count');
        $syncedFavorites = $this->syncKeyBuffer(self::FAVORITES_BUFFER_KEY, 'favorites_count');

        return [
            'likes_synced' => $syncedLikes,
            'favorites_synced' => $syncedFavorites,
        ];
    }

    /**
     * 单个 Hash 缓冲区的原子重命名快照与批量落库
     */
    protected function syncKeyBuffer(string $bufferKey, string $column): int
    {
        $syncingKey = $bufferKey . ':syncing:' . microtime(true);

        try {
            if (! Redis::exists($bufferKey)) {
                return 0;
            }
            // 原子重命名，隔绝同步快照与新的写入
            Redis::rename($bufferKey, $syncingKey);
        } catch (\Throwable) {
            return 0;
        }

        try {
            $data = Redis::hgetall($syncingKey);
            if (empty($data)) {
                Redis::del($syncingKey);
                return 0;
            }

            $count = 0;
            foreach ($data as $videoId => $delta) {
                $delta = (int) $delta;
                if ($delta === 0) {
                    continue;
                }

                $vid = (int) $videoId;
                if ($delta > 0) {
                    Video::query()->whereKey($vid)->increment($column, $delta);
                } else {
                    $abs = abs($delta);
                    $affected = Video::query()->whereKey($vid)->where($column, '>=', $abs)->decrement($column, $abs);
                    if ($affected === 0) {
                        Video::query()->whereKey($vid)->update([$column => 0]);
                    }
                }
                $count++;
            }

            Redis::del($syncingKey);
            return $count;
        } catch (\Throwable $e) {
            Log::error("Failed to sync {$bufferKey} buffer to database", [
                'error' => $e->getMessage(),
                'syncing_key' => $syncingKey,
            ]);

            // 发生异常时将未同步数据回滚合并回主缓冲区，防止丢失
            try {
                $data = Redis::hgetall($syncingKey);
                foreach ($data as $videoId => $delta) {
                    Redis::hincrby($bufferKey, (string) $videoId, (int) $delta);
                }
                Redis::del($syncingKey);
            } catch (\Throwable) {
            }

            throw $e;
        }
    }

    /**
     * 批量校准所有视频的点赞数与收藏数（基于 Laravel Love 反应事实表全量校准）
     *
     * @return int 同步处理的视频数量
     */
    public function syncAllCounts(): int
    {
        // 1. 先将当前 Redis 缓冲区的数据全部刷库
        $this->syncBufferToDatabase();

        // 2. 批量从 love_reactions 事实表全量校准
        $updatedTotal = 0;

        Video::query()
            ->whereNotNull('love_reactant_id')
            ->chunkById(200, function ($videos) use (&$updatedTotal) {
                $reactantIds = $videos->pluck('love_reactant_id')->filter()->all();
                if (empty($reactantIds)) {
                    return;
                }

                $likeCounts = DB::table('love_reactions')
                    ->join('love_reaction_types', 'love_reactions.reaction_type_id', '=', 'love_reaction_types.id')
                    ->whereIn('love_reactions.reactant_id', $reactantIds)
                    ->where('love_reaction_types.name', 'Like')
                    ->groupBy('love_reactions.reactant_id')
                    ->select('love_reactions.reactant_id', DB::raw('count(*) as count'))
                    ->pluck('count', 'reactant_id')
                    ->all();

                $collectCounts = DB::table('love_reactions')
                    ->join('love_reaction_types', 'love_reactions.reaction_type_id', '=', 'love_reaction_types.id')
                    ->whereIn('love_reactions.reactant_id', $reactantIds)
                    ->where('love_reaction_types.name', 'VideoCollect')
                    ->groupBy('love_reactions.reactant_id')
                    ->select('love_reactions.reactant_id', DB::raw('count(*) as count'))
                    ->pluck('count', 'reactant_id')
                    ->all();

                foreach ($videos as $video) {
                    $realLikes = (int) ($likeCounts[$video->love_reactant_id] ?? 0);
                    $realCollects = (int) ($collectCounts[$video->love_reactant_id] ?? 0);

                    if ($video->likes_count !== $realLikes || $video->favorites_count !== $realCollects) {
                        Video::query()->whereKey($video->id)->update([
                            'likes_count' => $realLikes,
                            'favorites_count' => $realCollects,
                        ]);
                    }
                    $updatedTotal++;
                }
            });

        return $updatedTotal;
    }
}

