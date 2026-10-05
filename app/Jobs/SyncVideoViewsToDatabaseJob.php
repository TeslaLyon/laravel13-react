<?php

namespace App\Jobs;

use App\Services\VideoViewService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncVideoViewsToDatabaseJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries = 2;
    public string $task_name = '批量同步视频浏览量至数据库';

    public function handle(VideoViewService $service): void
    {
        try {
            $syncedCount = $service->syncBufferToDatabase();
            if ($syncedCount > 0) {
                Log::info("Successfully synced {$syncedCount} videos' view counts to database.");
            }
        } catch (\Throwable $e) {
            Log::error('SyncVideoViewsToDatabaseJob failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }
}

