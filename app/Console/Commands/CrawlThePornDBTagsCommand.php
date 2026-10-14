<?php

namespace App\Console\Commands;

use App\Services\Crawler\ThePornDBCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrawlThePornDBTagsCommand extends Command
{
    /**
     * 控制台命令签名
     *
     * @var string
     */
    protected $signature = 'crawler:tpdb-tags
                            {site=vixen : 目标片商/厂牌 slug，例如 vixen, blacked, deeper, tushy, slayed 等}
                            {--page= : 指定抓取的分页页码（不传默认从第 1 页开始自动向后遍历至最后一页）}
                            {--all : 配合 --page 使用，从指定页开始循环抓取至最后一页}
                            {--limit= : 每页处理视频上限数量（默认全部）}
                            {--delay=2 : 抓取详情页间隔秒数，防触发频繁风控（默认 2 秒）}
                            {--skip-tagged : 如果本地视频已有标签，则跳过详情页抓取，大幅提高同步效率}
                            {--dry-run : 演练模式，仅打印解析与匹配结果，不写入数据库}
                            {--force-login : 强制执行 ThePornDB 自动登录并刷新 Redis 登录凭证}
                            {--email= : ThePornDB 登录账号（留空默认读取配置或环境变量）}
                            {--password= : ThePornDB 登录密码（留空默认读取配置或环境变量）}
                            {--file= : 指定本地列表页 HTML 文件（如 site-list.html）进行离线测试}
                            {--detail-file= : 指定本地详情页 HTML 文件（如 detail.html）进行离线测试}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '爬取 ThePornDB (TPDB) 厂牌视频列表并进入详情页同步标签(Tags)至本地视频';

    /**
     * 执行控制台命令
     */
    public function handle(ThePornDBCrawlerService $service): int
    {
        $siteSlug = strtolower(trim((string) $this->argument('site')));
        $pageOption = $this->option('page');

        if ($pageOption === null || $pageOption === '') {
            $startPage = 1;
            $crawlAll = true;
        } else {
            $startPage = max(1, (int) $pageOption);
            $crawlAll = (bool) $this->option('all');
        }

        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : null;
        $delay = max(0, (int) ($this->option('delay') ?? 2));
        $skipTagged = (bool) $this->option('skip-tagged');
        $dryRun = (bool) $this->option('dry-run');
        $forceLogin = (bool) $this->option('force-login');
        $email = $this->option('email');
        $password = $this->option('password');
        $localFilePath = $this->option('file');
        $localDetailFilePath = $this->option('detail-file');

        // 关闭数据库查询日志，防止大规模跑批内存溢出
        DB::disableQueryLog();

        // 注入详细步骤日志回调，实时输出至终端控制台
        $service->setLogger(function (string $message, string $level = 'info') {
            match ($level) {
                'error'   => $this->error("  {$message}"),
                'warn'    => $this->warn("  {$message}"),
                'comment' => $this->comment("  {$message}"),
                'line'    => $this->line("  {$message}"),
                default   => $this->info("  {$message}"),
            };
        });

        // 若指定了强制登录选项
        if ($forceLogin) {
            $this->info("🔑 收到 --force-login 指令，正在启动登录流程...");
            $loginOk = $service->login($email, $password, force: true);
            if (!$loginOk) {
                $this->error("❌ ThePornDB 强制登录失败，任务中止！");
                return Command::FAILURE;
            }
        }

        $authUser = null;
        try {
            $isAuth = \Illuminate\Support\Facades\Redis::get('crawler:tpdb_authenticated');
            $cachedCookie = \Illuminate\Support\Facades\Redis::get('crawler:cookie_str:theporndb.net');
            if ($isAuth && !empty($cachedCookie)) {
                $authUser = \Illuminate\Support\Facades\Redis::get('crawler:tpdb_auth_user');
            }
        } catch (\Throwable) {}

        $this->info("==================================================");
        $this->info("🚀 开始启动 ThePornDB 标签同步任务");
        $this->line("🎯 目标厂牌: <comment>{$siteSlug}</comment>");
        if ($authUser) {
            $this->line("👤 账号状态: <info>已登录 ({$authUser})</info>");
        } else {
            $this->line("👤 账号状态: <comment>按需自动登录</comment>");
        }
        if ($localFilePath) {
            $this->line("📁 本地列表文件: <info>{$localFilePath}</info>");
        } else {
            $this->line("📄 起始页码: <comment>{$startPage}</comment>" . ($crawlAll ? " (自动抓取至最后一页)" : " (仅抓取当前页)"));
        }
        if ($localDetailFilePath) {
            $this->line("📁 本地详情文件: <info>{$localDetailFilePath}</info>");
        }
        $this->line("⏱️ 详情间隔: <comment>{$delay} 秒</comment>");
        $this->line("⏩ 跳过已打标: <comment>" . ($skipTagged ? '开启' : '关闭') . "</comment>");
        $this->line("🛡️ 运行模式: <comment>" . ($dryRun ? '演练模式 (Dry-Run)' : '正式入库') . "</comment>");
        $this->info("==================================================");

        $currentPage = $startPage;
        $lastPage = $startPage;

        $stats = [
            'total_scenes_found' => 0,
            'matched_videos'     => 0,
            'synced_videos'      => 0,
            'skipped_tagged'     => 0,
            'unmatched_videos'   => 0,
            'failed_scenes'      => 0,
        ];

        do {
            $this->newLine();
            $this->info("🔍 [第 {$currentPage} 页] 正在拉取厂牌列表...");

            $localHtml = null;
            if ($localFilePath) {
                if (!file_exists($localFilePath) && !file_exists(base_path($localFilePath))) {
                    $this->error("❌ 本地列表文件不存在: {$localFilePath}");
                    return Command::FAILURE;
                }
                $path = file_exists($localFilePath) ? $localFilePath : base_path($localFilePath);
                $localHtml = file_get_contents($path);
                $crawlAll = false; // 本地单文件测试无需翻页
            }

            $listResult = $service->crawlSitePage($siteSlug, $currentPage, $localHtml);
            if (!$listResult['success']) {
                $this->error("❌ [第 {$currentPage} 页] " . ($listResult['message'] ?? '拉取失败'));
                if (!empty($listResult['debug_info'])) {
                    $this->warn("   ℹ️ 诊断信息: " . $listResult['debug_info']);
                }
                if (!$crawlAll) {
                    return Command::FAILURE;
                }
                $currentPage++;
                continue;
            }

            $lastPage = $listResult['last_page'] ?? $currentPage;
            $scenes = $listResult['scenes'] ?? [];
            $sceneCount = count($scenes);

            $this->line("📦 本页发现 <comment>{$sceneCount}</comment> 部视频 (全网共 {$listResult['total']} 部，总页数: {$lastPage})");

            if ($limit && $limit < $sceneCount) {
                $scenes = array_slice($scenes, 0, $limit);
                $this->line("⚠️ 触发 --limit 限制，本页截取前 <comment>{$limit}</comment> 部处理");
            }

            foreach ($scenes as $index => $sceneItem) {
                $stats['total_scenes_found']++;
                $sceneIndex = $index + 1;
                $sceneTitle = $sceneItem['title'] ?? '未知标题';
                $sceneLink = $sceneItem['link'] ?? '';

                $this->line(sprintf("  [%d/%d] 🎬 检索本地库: <info>%s</info>", $sceneIndex, count($scenes), $sceneTitle));

                // 1. 优先在本地数据库中寻找匹配的 Video 记录
                $video = $service->findVideoForScene($sceneItem, $siteSlug);
                if (!$video) {
                    $this->comment("     ⚠️ 本地数据库未匹配到该视频，跳过详情抓取");
                    $stats['unmatched_videos']++;
                    continue;
                }

                $stats['matched_videos']++;

                // 2. 检查是否跳过已打标视频
                if ($skipTagged && $video->tags()->exists()) {
                    $this->line("     ⏭️ 该视频本地已有标签，根据 --skip-tagged 跳过");
                    $stats['skipped_tagged']++;
                    continue;
                }

                if (empty($sceneLink) && empty($localDetailFilePath)) {
                    $this->error("     ❌ 视频缺少详情页链接");
                    $stats['failed_scenes']++;
                    continue;
                }

                // 3. 抓取详情页（支持本地 detail.html 文件注入）
                $localDetailHtml = null;
                if ($localDetailFilePath) {
                    $dPath = file_exists($localDetailFilePath) ? $localDetailFilePath : base_path($localDetailFilePath);
                    if (file_exists($dPath)) {
                        $localDetailHtml = file_get_contents($dPath);
                    }
                }

                $this->line("     🌐 正在请求详情页: <comment>" . ($localDetailHtml ? "本地注入: {$localDetailFilePath}" : $sceneLink) . "</comment>");
                $sceneDetail = $service->crawlSceneDetail($sceneLink ?: 'local', $localDetailHtml);
                if (!$sceneDetail) {
                    $this->error("     ❌ 详情页解析失败，跳过");
                    $stats['failed_scenes']++;
                    continue;
                }

                $tagsCount = count($sceneDetail['tags'] ?? []);
                $markersCount = count($sceneDetail['markers'] ?? []);
                $tagsData = $service->extractCombinedTags($sceneDetail);
                if (empty($tagsData)) {
                    $this->comment("     ℹ️ 详情页无标签及标记数据 (tags 与 markers 均为空)");
                    continue;
                }

                // 4. 同步标签至本地视频
                $syncResult = $service->syncTagsToVideo($video, $tagsData, $dryRun);
                $applied = $syncResult['tags'] ?? [];
                $tagNamesZh = array_map(fn($t) => $t['name_zh'] ?? $t['name'], $applied);

                $stats['synced_videos']++;

                if ($dryRun) {
                    $this->comment(sprintf(
                        "     🛡️ [演练模式] 模拟解析出 %d 个标签 (未写入数据库，去掉 --dry-run 即可正式入库): [%s]",
                        count($applied),
                        implode(', ', $tagNamesZh)
                    ));
                } else {
                    $this->info(sprintf(
                        "     ✅ [正式入库] 成功新增/关联 %d 个标签至视频 (tags: %d, markers: %d): [%s]",
                        count($applied),
                        $tagsCount,
                        $markersCount,
                        implode(', ', $tagNamesZh)
                    ));
                }

                // 避免频繁请求封禁
                if ($delay > 0) {
                    sleep($delay);
                }
            }

            $currentPage++;

        } while ($crawlAll && $currentPage <= $lastPage);

        $this->newLine();
        $this->info("================ 任务执行统计 ================");
        $this->table(
            ['指标项', '统计数值'],
            [
                ['扫描 TPDB 场景数', $stats['total_scenes_found']],
                ['成功匹配本地视频数', $stats['matched_videos']],
                ['已同步标签视频数', $stats['synced_videos']],
                ['跳过已打标视频数', $stats['skipped_tagged']],
                ['未在本地找到的视频', $stats['unmatched_videos']],
                ['抓取失败的详情页数', $stats['failed_scenes']],
            ]
        );
        $this->info("🎉 ThePornDB 标签同步处理完成！");

        return Command::SUCCESS;
    }
}

