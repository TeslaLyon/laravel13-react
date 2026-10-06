<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ChannelSubscriptionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncChannelSubscribersToDatabaseJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries = 2;
    public string $task_name = '批量同步片商订阅量至数据库';

    public function handle(ChannelSubscriptionService $service): void
    {
        try {
            $syncedCount = $service->syncBufferToDatabase();
            if ($syncedCount > 0) {
                Log::info("Successfully synced {$syncedCount} channels' subscriber counts to database.");
            }
        } catch (\Throwable $e) {
            Log::error('SyncChannelSubscribersToDatabaseJob failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }
}
