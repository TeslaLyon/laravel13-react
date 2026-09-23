<?php

namespace App\Console\Commands;

use App\Jobs\CrawlProject1VideosJob;
use App\Models\Channel;
use App\Services\Crawler\Project1VideoCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class CrawlProject1HistoryCommand extends Command
{
    /**
     * 控制台命令签名
     *
     * @var string
     */
    protected $signature = 'crawler:project1-history
                            {channel=brazzers : 片商 slug（默认 brazzers）}
                            {--start-page= : 起始抓取页码（默认自动按断点续爬或从第 1 页开始）}
                            {--end-page= : 结束抓取页码（留空则自动从接口元数据计算并爬取至最后一页）}
                            {--limit=24 : 每页拉取数量（默认 24 条）}
                            {--delay=1 : 每页之间的休眠间隔秒数（默认 1 秒，防封禁与降低服务器瞬时负载）}
                            {--resume : 开启断点续爬模式（自动从上次成功记录的页码继续）}
                            {--reset : 清除已记录的断点续爬进度}
                            {--queue : 将抓取任务批量派发至 Horizon 队列平滑异步执行，而非在当前前台执行}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '针对 Project1Service 片商（如 Brazzers）全量历史视频数据的稳健循环爬虫（带内存防爆、断点续爬、异常隔离与失败记录）';

    /**
     * 执行控制台命令
     */
    public function handle(Project1VideoCrawlerService $crawlerService): int
    {
        $channelSlug = trim($this->argument('channel') ?: 'brazzers');
        $channel = Channel::where('slug', $channelSlug)->where('data_crawl_type', 1)->first();

        if (!$channel) {
            $this->error("❌ 片商 [{$channelSlug}] 未找到或 data_crawl_type 不为 1！");
            return self::FAILURE;
        }

        $cacheKey = "crawler:history:{$channelSlug}:last_page";

        // 1. 处理进度重置
        if ($this->option('reset')) {
            Cache::forget($cacheKey);
            $this->warn("🔄 已成功清除片商 [{$channelSlug}] 的历史断点记录！");
        }

        $limit = max(1, (int) ($this->option('limit') ?: 24));
        $delay = max(0, (int) ($this->option('delay') ?? 1));

        // 2. 计算起始页码
        $startPageOpt = $this->option('start-page');
        if ($startPageOpt !== null) {
            $startPage = max(1, (int) $startPageOpt);
        } elseif ($this->option('resume')) {
            $lastSavedPage = (int) Cache::get($cacheKey, 0);
            $startPage = $lastSavedPage > 0 ? $lastSavedPage + 1 : 1;
            $this->info("⏩ 检测到断点进度，将自动从第 [{$startPage}] 页继续抓取...");
        } else {
            $startPage = 1;
        }

        // 3. 首次探测接口获取总数与总页数元数据
        $this->info("🔍 正在探测片商 [{$channelSlug}] 的总视频数与页码结构...");
        $firstProbe = $crawlerService->crawlSingleChannel($channel, $limit, page: $startPage);

        if (!$firstProbe['success'] && empty($firstProbe['total'])) {
            $this->error("❌ 无法连接到接口或获取片商元数据: " . ($firstProbe['message'] ?? '未知错误'));
            return self::FAILURE;
        }

        $totalRecords = $firstProbe['total'] ?? 0;
        $totalRemotePages = $firstProbe['total_pages'] ?: (int) ceil($totalRecords / $limit);

        $endPageOpt = $this->option('end-page');
        $endPage = $endPageOpt !== null ? min((int) $endPageOpt, $totalRemotePages) : $totalRemotePages;

        if ($startPage > $endPage) {
            $this->warn("⚠️ 起始页码 ({$startPage}) 大于结束页码 ({$endPage})，所有历史数据已全部抓取完毕！");
            return self::SUCCESS;
        }

        $totalPagesToProcess = $endPage - $startPage + 1;

        $this->newLine();
        $this->info("================================================================================");
        $this->info("🚀 Project1 历史全量数据循环抓取引擎启动");
        $this->info("片商: [{$channelSlug}] | 远端总数据量: {$totalRecords} 条 | 总页数: {$totalRemotePages} 页");
        $this->info("本次处理范围: 第 {$startPage} 页 ～ 第 {$endPage} 页 (共需处理 {$totalPagesToProcess} 页)");
        $this->info("分页大小: {$limit} 条/页 | 间隔延迟: {$delay} 秒/页");
        $this->info("================================================================================");
        $this->newLine();

        // 4. 模式 A：派发到 Horizon 队列异步平滑处理
        if ($this->option('queue')) {
            return $this->dispatchToQueue($channelSlug, $limit, $startPage, $endPage, $delay);
        }

        // 5. 模式 B：当前 CLI 进程循环稳健执行
        return $this->runCliLoop($crawlerService, $channel, $limit, $startPage, $endPage, $delay, $cacheKey, $firstProbe);
    }

    /**
     * 前台命令行循环执行（内存受控、断点持久化、错误隔离）
     */
    protected function runCliLoop(
        Project1VideoCrawlerService $crawlerService,
        Channel $channel,
        int $limit,
        int $startPage,
        int $endPage,
        int $delay,
        string $cacheKey,
        array $firstProbe
    ): int {
        // 🔥 关键防爆核心 1：关闭数据库查询日志，防止数十万次 SQL 缓存撑爆 PHP 内存
        DB::disableQueryLog();

        $startTime = microtime(true);
        $totalSuccess = 0;
        $totalFailed = 0;
        $totalSkipped = 0;
        $failedPages = [];

        for ($page = $startPage; $page <= $endPage; $page++) {
            $pageStartTime = microtime(true);

            // 第一页如果刚刚探测过，直接复用其入库结果，无需重复请求接口
            if ($page === $startPage && isset($firstProbe['success_count'])) {
                $res = $firstProbe;
            } else {
                // 每页自带网络层重试机制
                $res = $this->crawlPageWithRetry($crawlerService, $channel, $limit, $page);
            }

            if ($res['success']) {
                $totalSuccess += $res['success_count'] ?? 0;
                $totalFailed  += $res['failed_count'] ?? 0;
                $totalSkipped += $res['skipped_count'] ?? 0;

                // 记录成功处理完的断点到 Cache
                Cache::forever($cacheKey, $page);
            } else {
                $failedPages[] = $page;
                $this->error("❌ [第 {$page} 页] 抓取失败: " . ($res['message'] ?? '接口异常'));
            }

            // 🔥 关键防爆核心 2：强制触发垃圾回收周期，回收 Eloquent 模型与关联对象内存
            gc_collect_cycles();

            // 实时监控内存
            $memMb = round(memory_get_usage(true) / 1024 / 1024, 2);
            $pageElapsed = round(microtime(true) - $pageStartTime, 2);
            $percent = round((($page - $startPage + 1) / ($endPage - $startPage + 1)) * 100, 1);

            $this->line(sprintf(
                "[%s%%] 第 %d/%d 页 | 耗时: %ss | 成功: <info>%d</info> | 失败: <fg=red>%d</> | 跳过: %d | 内存: <comment>%s MB</comment>",
                str_pad((string)$percent, 5, ' ', STR_PAD_LEFT),
                $page,
                $endPage,
                $pageElapsed,
                $res['success_count'] ?? 0,
                $res['failed_count'] ?? 0,
                $res['skipped_count'] ?? 0,
                $memMb
            ));

            // 安全休眠冷却（防止触发 Cloudflare 频控）
            if ($page < $endPage && $delay > 0) {
                sleep($delay);
            }
        }

        $totalElapsed = round(microtime(true) - $startTime, 2);

        $this->newLine();
        $this->info("================================================================================");
        $this->info("🎉 历史数据爬取任务处理完成！");
        $this->info("总耗时: {$totalElapsed} 秒 | 处理页数: " . ($endPage - $startPage + 1) . " 页");
        $this->info("入库成功: {$totalSuccess} 条 | 字段/结构失败: {$totalFailed} 条 | 跳过 (gay/非scene): {$totalSkipped} 条");

        $failureFile = storage_path("logs/crawler_failures/{$channel->slug}.jsonl");
        if (file_exists($failureFile) && $totalFailed > 0) {
            $this->newLine();
            $this->warn("⚠️ 注意：共有 {$totalFailed} 条早期视频在入库时遇到结构异常，原始 Payload 已完整保留至：");
            $this->line("👉 {$failureFile}");
            $this->line("💡 提示：该文件为逐行 JSON 格式，绝无任何数据丢失，可随时查看缺失的字段详情！");
        }

        if (!empty($failedPages)) {
            $this->newLine();
            $this->error("🚨 以下页码因网络或远端接口 5xx 失败未成功抓取: " . implode(', ', $failedPages));
            $this->line("👉 可使用以下命令单独补跑失败页码，例如：");
            $firstFailed = $failedPages[0];
            $this->line("   php artisan crawler:project1-history {$channel->slug} --start-page={$firstFailed} --end-page={$firstFailed}");
        }

        $this->info("================================================================================");

        return self::SUCCESS;
    }

    /**
     * 带指数退避的单页爬取重试
     */
    protected function crawlPageWithRetry(Project1VideoCrawlerService $crawlerService, Channel $channel, int $limit, int $page, int $maxRetries = 3): array
    {
        $lastResult = ['success' => false, 'message' => '未知错误'];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $result = $crawlerService->crawlSingleChannel($channel, $limit, page: $page);
                if ($result['success']) {
                    return $result;
                }
                $lastResult = $result;
            } catch (Throwable $e) {
                $lastResult = ['success' => false, 'message' => $e->getMessage()];
            }

            if ($attempt < $maxRetries) {
                $retrySleep = $attempt * 2;
                $this->warn("   ⚠️ 第 {$page} 页请求异常，等待 {$retrySleep} 秒后进行第 " . ($attempt + 1) . " 次重试...");
                sleep($retrySleep);
            }
        }

        return $lastResult;
    }

    /**
     * 将每一页转换为独立 Job 派发至 Horizon 队列
     */
    protected function dispatchToQueue(string $channelSlug, int $limit, int $startPage, int $endPage, int $delay): int
    {
        $totalPages = $endPage - $startPage + 1;
        $this->info("📬 正在将 {$totalPages} 个页面的抓取任务按每 {$delay} 秒一个的节奏推入 Horizon 队列...");

        $progressBar = $this->output->createProgressBar($totalPages);
        $progressBar->start();

        $delaySeconds = 0;
        for ($page = $startPage; $page <= $endPage; $page++) {
            CrawlProject1VideosJob::dispatch($channelSlug, $limit, $page)
                ->delay(now()->addSeconds($delaySeconds));

            $delaySeconds += $delay;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("✅ 成功派发 {$totalPages} 个队列任务！");
        $this->line("💡 提示：所有任务将由 Horizon 工作进程平稳消费。");
        $this->line("👉 可在 Horizon 控制台 (https://你的域名/" . config('horizon.path', 'horizon') . ") 实时观察每个页面的消费进度。");

        return self::SUCCESS;
    }
}

