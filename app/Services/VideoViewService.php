<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class VideoViewService
{
    /**
     * Redis 浏览量累加缓冲 Hash Key
     */
    public const BUFFER_KEY = 'video:views:buffer';

    /**
     * 防抖窗口时间（秒）- 同一访客/IP在此时间内重复打开不计入有效浏览
     */
    public const DEDUP_SECONDS = 3600;

    /**
     * 记录一次有效视频浏览（防抖 + Redis 内存极速原子累加，零阻塞数据库）
     */
    public function recordView(Video|int $video, Request $request): bool
    {
        $videoId = $video instanceof Video ? $video->id : (int) $video;
        if ($videoId <= 0) {
            return false;
        }

        // 1. 获取访客防抖唯一标识（已登录用用户 ID，未登录用 IP + UserAgent 散列）
        $user = $request->user();
        $identifier = $user
            ? "user:{$user->id}"
            : 'ip:' . sha1($request->ip() . '|' . ($request->header('User-Agent') ?? ''));

        $dedupKey = "video:view_lock:{$videoId}:{$identifier}";

        // 2. 利用原子写入判定窗口内是否已访问过
        // Cache::add 仅在 key 不存在时写入并返回 true，若已存在则返回 false
        $isFirstView = Cache::add($dedupKey, 1, now()->addSeconds(self::DEDUP_SECONDS));
        if (! $isFirstView) {
            return false;
        }

        // 3. 内存原子自增（耗时 < 0.5ms，不产生数据库行锁）
        try {
            Redis::hincrby(self::BUFFER_KEY, (string) $videoId, 1);
        } catch (\Throwable $e) {
            Log::warning('Redis hincrby video views failed, falling back to direct db increment', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);

            // 极端情况下若 Redis 不可用，降级直接执行数据库自增
            Video::whereKey($videoId)->increment('views_count');
        }

        return true;
    }

    /**
     * 获取视频当前实时浏览量（数据库值 + Redis 缓冲区增量）
     */
    public function getViewCount(Video $video): int
    {
        $buffered = 0;

        try {
            $buffered = (int) Redis::hget(self::BUFFER_KEY, (string) $video->id);
        } catch (\Throwable) {
            // 忽略 Redis 异常
        }

        return (int) $video->views_count + $buffered;
    }

    /**
     * 格式化浏览量展示（例如：1.2万、850）
     */
    public static function format(int $count): string
    {
        if ($count < 10000) {
            return (string) max(0, $count);
        }

        $formatted = round($count / 10000, 1);

        return $formatted . '万';
    }

    /**
     * 将 Redis 缓冲区的浏览量批量落库同步到 PostgreSQL
     * 采用原子 rename 机制，确保同步期间新产生的浏览量完全不丢失
     */
    public function syncBufferToDatabase(): int
    {
        $syncingKey = self::BUFFER_KEY . ':syncing:' . microtime(true);

        try {
            // 1. 判断当前缓冲区是否存在数据
            if (! Redis::exists(self::BUFFER_KEY)) {
                return 0;
            }

            // 2. 原子重命名，隔绝同步快照与新的写入
            Redis::rename(self::BUFFER_KEY, $syncingKey);
        } catch (\Throwable $e) {
            // 常见情况是 key 不存在（并发或其他定时任务刚处理完）
            return 0;
        }

        try {
            // 3. 获取快照中的所有视频浏览增量
            $viewsData = Redis::hgetall($syncingKey);
            if (empty($viewsData)) {
                Redis::del($syncingKey);

                return 0;
            }

            $syncedCount = 0;

            // 4. 批量回写数据库
            foreach ($viewsData as $videoId => $incCount) {
                $incCount = (int) $incCount;
                if ($incCount > 0) {
                    Video::whereKey((int) $videoId)->increment('views_count', $incCount);
                    $syncedCount++;
                }
            }

            // 5. 清理已同步的临时快照
            Redis::del($syncingKey);

            return $syncedCount;
        } catch (\Throwable $e) {
            Log::error('Failed to sync video views buffer to database', [
                'error' => $e->getMessage(),
                'syncing_key' => $syncingKey,
            ]);

            // 若发生异常，尝试将数据回滚合并回主缓冲区，防止丢失
            try {
                $viewsData = Redis::hgetall($syncingKey);
                foreach ($viewsData as $videoId => $incCount) {
                    Redis::hincrby(self::BUFFER_KEY, (string) $videoId, (int) $incCount);
                }
                Redis::del($syncingKey);
            } catch (\Throwable) {
            }

            throw $e;
        }
    }
}

