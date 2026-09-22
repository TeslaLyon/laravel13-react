<?php

namespace App\Console\Commands;

use App\Services\Crawler\Project1VideoCrawlerService;
use Illuminate\Console\Command;

class project1serviceApiToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'p1s:token {channel : 片商 slug} {--force : 强制从官网重新拉取}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取或刷新 Project1Service 片商 Token';

    /**
     * Execute the console command.
     */
    public function handle(Project1VideoCrawlerService $crawlerService): int
    {
        $channel = $this->argument('channel');
        $force = (bool) $this->option('force');

        $this->info("正在获取片商 [{$channel}] 的 Token" . ($force ? " (强制从官网抓取)..." : "..."));

        $token = $crawlerService->resolveChannelToken($channel, $force);

        if ($token) {
            $this->info("✅ Token 获取成功：\n" . $token);
            return self::SUCCESS;
        }

        $this->error("❌ 获取 Token 失败，请检查片商配置及官网可访问性。");
        return self::FAILURE;
    }
}
