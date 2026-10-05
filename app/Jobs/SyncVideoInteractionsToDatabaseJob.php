<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\VideoInteractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncVideoInteractionsToDatabaseJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries = 2;
    public string $task_name = '批量同步视频点赞与收藏量至数据库';

    public function handle(VideoInteractionService $service): void
    {
        try {
            $result = $service->syncBufferToDatabase();
            $likes = $result['likes_synced'] ?? 0;
            $favorites = $result['favorites_synced'] ?? 0;

            if ($likes > 0 || $favorites > 0) {
                Log::info("Successfully synced interactions to database: {$likes} likes, {$favorites} favorites.");
            }
        } catch (\Throwable $e) {
            Log::error('SyncVideoInteractionsToDatabaseJob failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }
}

