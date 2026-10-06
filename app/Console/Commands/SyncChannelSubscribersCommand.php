<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ChannelSubscriptionService;
use Illuminate\Console\Command;

class SyncChannelSubscribersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'channel:sync-subscribers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '全量校准片商订阅数（依据 love_reactions 事实表重新统计各片商的有效订阅数）';

    /**
     * Execute the console command.
     */
    public function handle(ChannelSubscriptionService $service): int
    {
        $this->info('开始全量校准片商订阅量...');

        $updatedCount = $service->syncAllCounts();

        $this->info("全量校准完成！共纠偏/更新了 {$updatedCount} 家片商的订阅数据。");

        return self::SUCCESS;
    }
}
