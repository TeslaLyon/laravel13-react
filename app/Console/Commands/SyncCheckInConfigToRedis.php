<?php

namespace App\Console\Commands;

use App\Services\CheckInConfigService;
use Illuminate\Console\Command;

class SyncCheckInConfigToRedis extends Command
{
    protected $signature = 'checkin:sync-redis';
    protected $description = '将本地签到奖励配置同步写入 Redis';

    public function handle(CheckInConfigService $configService): int
    {
        $this->info('正在同步签到配置至 Redis...');
        $configService->syncFromConfigFile();
        $this->info('同步完成！');
        return self::SUCCESS;
    }
}
