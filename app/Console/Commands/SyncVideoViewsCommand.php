<?php

namespace App\Console\Commands;

use App\Services\VideoViewService;
use Illuminate\Console\Command;

class SyncVideoViewsCommand extends Command
{
    /**
     * 控制台命令签名
     */
    protected $signature = 'videos:sync-views';

    /**
     * 控制台命令描述
     */
    protected $description = '将 Redis 缓冲池中的视频浏览量批量同步汇总至 PostgreSQL 数据库';

    /**
     * 执行控制台命令
     */
    public function handle(VideoViewService $service): int
    {
        $this->info('🚀 开始同步 Redis 视频浏览量缓冲区至数据库...');

        try {
            $count = $service->syncBufferToDatabase();

            if ($count > 0) {
                $this->info("✅ 同步完成！共汇总更新了 {$count} 个视频的浏览量。");
            } else {
                $this->comment('ℹ️ Redis 缓冲区暂无待同步的浏览量数据。');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("❌ 同步失败: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
