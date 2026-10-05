<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\VideoInteractionService;
use Illuminate\Console\Command;

class SyncVideoInteractionCountsCommand extends Command
{
    /**
     * 命令标识符
     *
     * @var string
     */
    protected $signature = 'video:sync-counts';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '从反应记录表校准同步全量视频的点赞数(likes_count)与收藏数(favorites_count)';

    /**
     * 执行命令
     */
    public function handle(VideoInteractionService $service): int
    {
        $this->info('正在校准并同步视频点赞与收藏统计数据...');

        $count = $service->syncAllCounts();

        $this->info("同步完成！共检查校准了 {$count} 个视频。");

        return self::SUCCESS;
    }
}
