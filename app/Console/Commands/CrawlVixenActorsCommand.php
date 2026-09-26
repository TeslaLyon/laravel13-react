<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\Crawler\VixenCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrawlVixenActorsCommand extends Command
{
    /**
     * 控制台命令签名
     *
     * @var string
     */
    protected $signature = 'crawler:vixen-actors
                            {channel? : 片商 slug（可选，未指定时自动遍历爬取所有 data_crawl_type=2 的片商）}
                            {--page= : 指定抓取的单页页码（不传则默认从第 1 页自动循环爬取至最后一页）}
                            {--all : 配合 --page 使用，从指定页开始向后自动循环抓取至最后一页}
                            {--no-images : 跳过图片下载优化，仅同步演员元数据（速度极快）}
                            {--delay=1 : 批量多页循环抓取时的间隔秒数}
                            {--force-images : 强制重新下载并覆盖已有演员的图片}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '抓取 Vixen 系列片商（data_crawl_type=2）的演员信息与图册';

    /**
     * 执行控制台命令
     */
    public function handle(VixenCrawlerService $crawlerService): int
    {
        $channelSlug = $this->argument('channel');
        $pageOption = $this->option('page');

        // 未传 --page 时，默认从第 1 页自动循环爬取至最后一页；
        // 若传了 --page=X，则默认只抓取该单页，除非同时显式指定了 --all
        if ($pageOption === null || $pageOption === '') {
            $startPage = 1;
            $all = true;
        } else {
            $startPage = max(1, (int) $pageOption);
            $all = (bool) $this->option('all');
        }

        $downloadImages = !$this->option('no-images');
        $delay = max(0, (int) ($this->option('delay') ?: 1));
        $forceImages = (bool) $this->option('force-images');

        // 关闭查询日志，保障长效循环内存安全
        DB::disableQueryLog();

        // 确定需要处理的片商列表
        if (!empty($channelSlug)) {
            $channels = Channel::where('slug', $channelSlug)
                ->where('data_crawl_type', 2)
                ->get();
            if ($channels->isEmpty()) {
                $this->error("❌ 未找到片商 [{$channelSlug}] 或其 data_crawl_type 不为 2");
                return self::FAILURE;
            }
        } else {
            $channels = Channel::where('data_crawl_type', 2)->get();
            if ($channels->isEmpty()) {
                $this->error("❌ 数据库中未找到任何 data_crawl_type 为 2 的片商！");
                return self::FAILURE;
            }
        }

        $totalChannels = $channels->count();
        $modeText = $all ? "自动循环爬取至最后一页" : "仅抓取第 {$startPage} 页";

        $this->info("🚀 启动 Vixen 系列演员爬虫"
            . ($channelSlug ? " [指定片商: {$channelSlug}]" : " [全部片商: 共 {$totalChannels} 个]")
            . " [起始页: 第 {$startPage} 页]"
            . " [模式: {$modeText}]"
            . ($downloadImages ? " [图片优化: 启用(R2/本地)]" : " [图片优化: 跳过]")
            . ($forceImages ? " [强制覆盖: 是]" : " [策略: 仅为空时补全图片，已有则保护跳过]")
        );

        $globalSuccess = 0;
        $globalFailed = 0;
        $globalSkipped = 0;

        foreach ($channels as $index => $channel) {
            $channelIndex = $index + 1;
            $this->newLine();
            $this->info("==================================================");
            $this->info("🎬 [{$channelIndex}/{$totalChannels}] 正在处理片商: {$channel->name} ({$channel->slug})");
            $this->info("==================================================");

            $currentPage = $startPage;

            do {
                $this->line("⏳ 正在抓取片商 [{$channel->slug}] 第 {$currentPage} 页演员数据...");
                $result = $crawlerService->crawlSingleChannelActors($channel, $currentPage, $downloadImages, $forceImages);

                if (!$result['success']) {
                    $this->error("⚠️ 片商 [{$channel->slug}] 第 {$currentPage} 页抓取失败: " . ($result['message'] ?? '未知错误'));
                    // 单个片商单页出错不中断其他片商，跳出当前片商
                    break;
                }

                $sCount = $result['success_count'] ?? 0;
                $fCount = $result['failed_count'] ?? 0;
                $skCount = $result['skipped_count'] ?? 0;

                $globalSuccess += $sCount;
                $globalFailed += $fCount;
                $globalSkipped += $skCount;

                if (isset($result['total'])) {
                    $this->line("📊 片商 [{$channel->slug}] 演员总量: {$result['total']} 人 | 总页数: {$result['total_pages']} 页 | 当前页: 第 {$currentPage} 页");
                }
                $this->table(
                    ['片商', '当前页', '成功入库', '失败', '跳过/重复'],
                    [[
                        $channel->slug,
                        $currentPage,
                        $sCount,
                        $fCount,
                        $skCount,
                    ]]
                );

                gc_collect_cycles();

                $hasNextPage = $result['has_next_page'] ?? false;
                if ($all && $hasNextPage) {
                    $currentPage++;
                    if ($delay > 0) {
                        sleep($delay);
                    }
                } else {
                    break;
                }
            } while ($all && $hasNextPage);

            $this->info("✅ 片商 [{$channel->slug}] 演员处理完成！");
        }

        $this->newLine();
        $this->info("🎉 全部任务执行完毕！共处理 {$totalChannels} 个片商。");
        $this->table(
            ['总片商数', '总成功入库', '总失败', '总跳过/重复'],
            [[
                $totalChannels,
                $globalSuccess,
                $globalFailed,
                $globalSkipped,
            ]]
        );

        return self::SUCCESS;
    }
}
