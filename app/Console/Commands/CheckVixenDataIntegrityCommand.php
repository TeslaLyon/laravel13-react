<?php

namespace App\Console\Commands;

use App\Models\Actor;
use App\Models\Channel;
use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckVixenDataIntegrityCommand extends Command
{
    /**
     * 控制台命令签名
     *
     * @var string
     */
    protected $signature = 'crawler:vixen-check-integrity
                            {channel? : 指定片商 slug（可选，未指定则检查所有 data_crawl_type=2 的片商）}
                            {--hours=24 : 检查最近 N 小时内爬取/更新的数据 (默认 24 小时)}
                            {--limit=50 : 抽查最大数量限制（0 表示不限数量）}
                            {--all : 忽略时间限制，进行全量抽查}
                            {--only-videos : 仅检查视频数据完整性}
                            {--only-actors : 仅检查演员数据完整性}
                            {--strict : 严格模式（将提示类警告也计入不合格）}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '全面检查 Vixen 系列片商刚爬取数据的完整性，排查缺少字段、空字段与图片异常，保障前端展示安全';

    /**
     * 执行控制台命令
     */
    public function handle(): int
    {
        $channelSlug = $this->argument('channel');
        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');
        $all = (bool) $this->option('all');
        $onlyVideos = (bool) $this->option('only-videos');
        $onlyActors = (bool) $this->option('only-actors');
        $strict = (bool) $this->option('strict');

        // 🌟 智能全量模式：如果传了 --all 且用户没有显式传递 --limit，则自动解除 50 条限制，全量检查库内所有数据
        if ($all && !$this->input->hasParameterOption('--limit')) {
            $limit = 0;
        }

        DB::disableQueryLog();

        // 1. 获取目标片商列表
        $channelQuery = Channel::where('data_crawl_type', 2);
        if (!empty($channelSlug)) {
            $channelQuery->where('slug', $channelSlug);
        }
        $channels = $channelQuery->get();

        if ($channels->isEmpty()) {
            $this->error("❌ 未找到符合条件的 Vixen 系列片商" . ($channelSlug ? " [{$channelSlug}]" : " (data_crawl_type=2)"));
            return self::FAILURE;
        }

        $channelIds = $channels->pluck('id')->toArray();
        $sinceTime = $all ? null : Carbon::now()->subHours(max(1, $hours));

        $this->newLine();
        $this->info("================================================================================");
        $this->info("🔍 Vixen 系列数据爬取完整性与前端展示安全专项检查");
        $this->info("================================================================================");
        $this->line("📌 检查目标: " . ($channelSlug ? "指定片商 [{$channelSlug}]" : "全部 Vixen 系列片商 (" . $channels->count() . " 个)"));
        $this->line("⏱️ 时间范围: " . ($all ? "全量数据抽查" : "最近 {$hours} 小时（" . ($sinceTime ? $sinceTime->toDateTimeString() : '') . " 至今）"));
        $this->line("🔢 数量上限: " . ($limit > 0 ? "最多 {$limit} 条" : "不限条数"));
        $this->line("🛡️ 严格模式: " . ($strict ? "开启 (警告亦视作不合格)" : "关闭 (仅严重缺陷视作不合格)"));
        $this->newLine();

        $videoIssues = [];
        $actorIssues = [];
        $totalVideosChecked = 0;
        $totalActorsChecked = 0;

        // 2. 检查视频数据完整性
        if (!$onlyActors) {
            $this->info("📹 [1/2] 正在校验视频数据及其扩展详情 (Videos & VideoDetails)...");
            
            $videoQuery = Video::whereIn('channel_id', $channelIds)
                ->with(['channel', 'videoDetail', 'actors']);

            if ($sinceTime) {
                $videoQuery->where('updated_at', '>=', $sinceTime);
            }

            $videoQuery->orderByDesc('updated_at');

            $totalVideosAvailable = (clone $videoQuery)->count();
            $totalVideosChecked = $limit > 0 ? min($totalVideosAvailable, $limit) : $totalVideosAvailable;

            $progressBar = $this->output->createProgressBar(max(1, $totalVideosChecked));
            $progressBar->start();

            // 🌟 采用分批 chunk(200) 流式扫描，杜绝全量查询时内存溢出 (OOM)
            $processedVideoCount = 0;
            (clone $videoQuery)->chunk(200, function ($videos) use (&$videoIssues, $progressBar, &$processedVideoCount, $limit) {
                foreach ($videos as $video) {
                    if ($limit > 0 && $processedVideoCount >= $limit) {
                        return false;
                    }

                    $issues = $this->inspectVideo($video);
                    if (!empty($issues)) {
                        $videoIssues[] = [
                            'video'  => $video,
                            'issues' => $issues,
                        ];
                    }

                    $processedVideoCount++;
                    $progressBar->advance();
                }
            });

            $progressBar->finish();
            $this->newLine(2);
        }

        // 3. 检查演员数据完整性
        if (!$onlyVideos) {
            $this->info("👤 [2/2] 正在校验演员基础数据及头像写真 (Actors & Images)...");

            $actorQuery = Actor::whereHas('videos', function ($q) use ($channelIds) {
                $q->whereIn('channel_id', $channelIds);
            })->withCount('videos');

            if ($sinceTime) {
                $actorQuery->where('updated_at', '>=', $sinceTime);
            }

            $actorQuery->orderByDesc('updated_at');

            $totalActorsAvailable = (clone $actorQuery)->count();
            $totalActorsChecked = $limit > 0 ? min($totalActorsAvailable, $limit) : $totalActorsAvailable;

            $progressBar = $this->output->createProgressBar(max(1, $totalActorsChecked));
            $progressBar->start();

            // 🌟 采用分批 chunk(200) 流式扫描
            $processedActorCount = 0;
            (clone $actorQuery)->chunk(200, function ($actors) use (&$actorIssues, $progressBar, &$processedActorCount, $limit) {
                foreach ($actors as $actor) {
                    if ($limit > 0 && $processedActorCount >= $limit) {
                        return false;
                    }

                    $issues = $this->inspectActor($actor);
                    if (!empty($issues)) {
                        $actorIssues[] = [
                            'actor'  => $actor,
                            'issues' => $issues,
                        ];
                    }

                    $processedActorCount++;
                    $progressBar->advance();
                }
            });

            $progressBar->finish();
            $this->newLine(2);
        }

        // 4. 输出校验报告汇总
        return $this->renderReport(
            $channels,
            $totalVideosChecked,
            $videoIssues,
            $totalActorsChecked,
            $actorIssues,
            $strict
        );
    }

    /**
     * 单个视频完整性校验核心算法
     *
     * @return array<array{level: string, field: string, message: string, impact: string}>
     */
    protected function inspectVideo(Video $video): array
    {
        $issues = [];

        // ----------------------------------------------------
        // A. 基础路由与标识字段（直接决定前端是否能正常寻址与渲染）
        // ----------------------------------------------------
        if (empty($video->slug)) {
            $issues[] = [
                'level'   => 'FATAL',
                'field'   => 'slug',
                'message' => 'slug 字段为空',
                'impact'  => '严重！无法生成前端路由 /videos/{slug}，用户点击将 404',
            ];
        }

        if (empty($video->name)) {
            $issues[] = [
                'level'   => 'FATAL',
                'field'   => 'name',
                'message' => '视频标题 name 为空',
                'impact'  => '严重！前端卡片与详情页标题空白',
            ];
        }

        if (empty($video->video_code)) {
            $issues[] = [
                'level'   => 'ERROR',
                'field'   => 'video_code',
                'message' => '统一番号 video_code 为空',
                'impact'  => '影响！前端番号标签无法展示，影响搜素与复制',
            ];
        }

        if (empty($video->channel_id) || !$video->channel) {
            $issues[] = [
                'level'   => 'FATAL',
                'field'   => 'channel_id',
                'message' => '片商关联不存在或 channel_id 为空',
                'impact'  => '严重！片商 Logo 与片商标签无法渲染，详情页关联报错',
            ];
        }

        if (empty($video->release_at)) {
            $issues[] = [
                'level'   => 'WARNING',
                'field'   => 'release_at',
                'message' => '发布日期 release_at 为空',
                'impact'  => '前端发布年份/日期角标无法展示，时间排序失效',
            ];
        }

        // ----------------------------------------------------
        // B. 核心图片资源校验（直接决定前端封面是否破图）
        // ----------------------------------------------------
        $listImg = $video->list_img;
        if (empty($listImg) || !is_array($listImg)) {
            $issues[] = [
                'level'   => 'FATAL',
                'field'   => 'list_img',
                'message' => '封面配置 list_img 为空或格式非数组',
                'impact'  => '严重！首页、分类页、相关推荐等所有列表封面黑块破图！',
            ];
        } else {
            // 深入检查首张图片是否有可用 URL
            $hasValidImageUrl = false;
            foreach ($listImg as $imgItem) {
                if (
                    !empty($imgItem['src']) ||
                    !empty($imgItem['highdpi']['double']) ||
                    !empty($imgItem['webp']['src']) ||
                    !empty($imgItem['webp']['highdpi']['double']) ||
                    !empty($imgItem['placeholder'])
                ) {
                    $hasValidImageUrl = true;
                    break;
                }
            }

            if (!$hasValidImageUrl) {
                $issues[] = [
                    'level'   => 'FATAL',
                    'field'   => 'list_img',
                    'message' => 'list_img 虽有数组但未找到任何有效图片链接 (src/highdpi/placeholder 均空)',
                    'impact'  => '严重！前端封面彻底无法加载破图！',
                ];
            }

            // 检查尺寸元数据
            $firstImg = $listImg[0] ?? [];
            if (empty($firstImg['width']) || empty($firstImg['height'])) {
                $issues[] = [
                    'level'   => 'NOTICE',
                    'field'   => 'list_img.dimensions',
                    'message' => '封面缺少 width/height 尺寸元数据',
                    'impact'  => '轻微！可能导致图片加载时产生微小布局位移 (CLS)',
                ];
            }
        }

        // ----------------------------------------------------
        // C. 视频预览校验（鼠标悬停轮播）
        // ----------------------------------------------------
        if (empty($video->preview)) {
            $issues[] = [
                'level'   => 'WARNING',
                'field'   => 'preview',
                'message' => '鼠标悬停预览 preview 字段为空',
                'impact'  => '影响！列表卡片鼠标悬停时无法触发多图轮播预览效果',
            ];
        } elseif (!str_starts_with($video->preview, '3<')) {
            $issues[] = [
                'level'   => 'WARNING',
                'field'   => 'preview',
                'message' => "preview 格式非 3< 开头的图片轮播规范 (当前: {$video->preview})",
                'impact'  => '可能无法被前端悬停预览组件正确解析',
            ];
        }

        // ----------------------------------------------------
        // D. 演员关联校验（演员标签与过滤）
        // ----------------------------------------------------
        if ($video->actors->isEmpty()) {
            $issues[] = [
                'level'   => 'ERROR',
                'field'   => 'actors',
                'message' => '未关联任何女演员 (actor_video 关联为空)',
                'impact'  => '影响！详情页“演员阵容”模块为空，演员主页无该视频',
            ];
        }

        // ----------------------------------------------------
        // E. 详情扩展表校验 (VideoDetail)
        // ----------------------------------------------------
        $detail = $video->videoDetail;
        if (!$detail) {
            $issues[] = [
                'level'   => 'FATAL',
                'field'   => 'video_details',
                'message' => '缺失一对一详情记录 VideoDetail',
                'impact'  => '严重！视频详情页无法正常拉取剧照与时长，页面渲染缺失',
            ];
        } else {
            // 剧照截图相册
            $screenImg = $detail->screen_img;
            if (empty($screenImg) || !is_array($screenImg)) {
                $issues[] = [
                    'level'   => 'ERROR',
                    'field'   => 'screen_img',
                    'message' => '详情剧照 screen_img 为空或非数组',
                    'impact'  => '影响！详情页下方“剧照相册/精彩截图”模块完全空白',
                ];
            }

            // 视频时长
            if (empty($detail->movie_length) || $detail->movie_length <= 0) {
                $issues[] = [
                    'level'   => 'WARNING',
                    'field'   => 'movie_length',
                    'message' => '视频时长 movie_length <= 0 或为空',
                    'impact'  => '影响！卡片和详情页的时长胶囊将显示 00:00',
                ];
            }

            // 简介
            if (empty(trim((string) $detail->description))) {
                $issues[] = [
                    'level'   => 'NOTICE',
                    'field'   => 'description',
                    'message' => '视频简介 description 为空',
                    'impact'  => '详情页剧情简介区域显示默认空白',
                ];
            }
        }

        // ----------------------------------------------------
        // F. 画质属性与 4K 逻辑自洽性
        // ----------------------------------------------------
        if (empty($video->max_quality)) {
            $issues[] = [
                'level'   => 'WARNING',
                'field'   => 'max_quality',
                'message' => '最高画质 max_quality 为空',
                'impact'  => '影响！视频卡片右上角无画质标称 (如 4K/1080p)',
            ];
        } else {
            // 4K 逻辑校验
            if ($video->max_quality === '2160p' && !$video->is_4k) {
                $issues[] = [
                    'level'   => 'WARNING',
                    'field'   => 'is_4k',
                    'message' => 'max_quality 为 2160p 但 is_4k 为 false',
                    'impact'  => '影响！4K 专属筛选或 4K 角标可能不会点亮',
                ];
            } elseif ($video->is_4k && $video->max_quality !== '2160p') {
                $issues[] = [
                    'level'   => 'WARNING',
                    'field'   => 'is_4k',
                    'message' => "is_4k 为 true 但 max_quality 为 [{$video->max_quality}]",
                    'impact'  => '画质字段冲突，前端角标显示混乱',
                ];
            }
        }

        return $issues;
    }

    /**
     * 单个演员完整性校验核心算法
     *
     * @return array<array{level: string, field: string, message: string, impact: string}>
     */
    protected function inspectActor(Actor $actor): array
    {
        $issues = [];

        if (empty($actor->slug)) {
            $issues[] = [
                'level'   => 'FATAL',
                'field'   => 'slug',
                'message' => '演员 slug 为空',
                'impact'  => '严重！无法生成演员个人页路由 /actors/{slug}',
            ];
        }

        if (empty($actor->name)) {
            $issues[] = [
                'level'   => 'FATAL',
                'field'   => 'name',
                'message' => '演员名字 name 为空',
                'impact'  => '严重！演员卡片与详情页名字空白',
            ];
        }

        // 头像与写真集
        $bootyImg = $actor->booty_img;
        if (empty($bootyImg) || !is_array($bootyImg)) {
            $issues[] = [
                'level'   => 'ERROR',
                'field'   => 'booty_img',
                'message' => '写真相册 booty_img 为空或非数组',
                'impact'  => '严重！前端演员列表与卡片头像破图空白！',
            ];
        } else {
            // 校验是否有首图有效 URL
            $hasValidImg = false;
            foreach ($bootyImg as $imgItem) {
                if (!empty($imgItem['src']) || !empty($imgItem['highdpi']['double']) || !empty($imgItem['placeholder'])) {
                    $hasValidImg = true;
                    break;
                }
            }

            if (!$hasValidImg) {
                $issues[] = [
                    'level'   => 'ERROR',
                    'field'   => 'booty_img',
                    'message' => 'booty_img 数组内未找到任何有效图片链接 (src/highdpi 均为空)',
                    'impact'  => '严重！演员卡片彻底无法加载图片！',
                ];
            }
        }

        return $issues;
    }

    /**
     * 渲染直观的可视化检查报告
     */
    protected function renderReport(
        Collection $channels,
        int $videoTotal,
        array $videoIssues,
        int $actorTotal,
        array $actorIssues,
        bool $strict
    ): int {
        $this->info("================================================================================");
        $this->info("📊 检查结果综合分析与评估报告");
        $this->info("================================================================================");

        $videoFailCount = count($videoIssues);
        $videoPassCount = $videoTotal - $videoFailCount;
        $videoPassRate  = $videoTotal > 0 ? round(($videoPassCount / $videoTotal) * 100, 1) : 100;

        $actorFailCount = count($actorIssues);
        $actorPassCount = $actorTotal - $actorFailCount;
        $actorPassRate  = $actorTotal > 0 ? round(($actorPassCount / $actorTotal) * 100, 1) : 100;

        $this->table(
            ['数据分类', '抽查总数', '完全健康合格数', '存在缺陷数', '完好率', '健康状态评级'],
            [
                [
                    '视频数据 (Videos)',
                    $videoTotal,
                    $videoPassCount,
                    $videoFailCount,
                    "{$videoPassRate}%",
                    $videoPassRate >= 98 ? '<info>优秀 (EXCELLENT)</info>' : ($videoPassRate >= 90 ? '<comment>良好 (GOOD)</comment>' : '<error>风险 (RISKY)</error>'),
                ],
                [
                    '演员数据 (Actors)',
                    $actorTotal,
                    $actorPassCount,
                    $actorFailCount,
                    "{$actorPassRate}%",
                    $actorPassRate >= 98 ? '<info>优秀 (EXCELLENT)</info>' : ($actorPassRate >= 90 ? '<comment>良好 (GOOD)</comment>' : '<error>风险 (RISKY)</error>'),
                ],
            ]
        );

        // 如果存在视频缺陷，列出详细问题清单
        if (!empty($videoIssues)) {
            $this->newLine();
            $this->error("🚨 发现以下视频存在缺陷项（按影响程度排序）：");

            $rows = [];
            foreach ($videoIssues as $item) {
                /** @var Video $v */
                $v = $item['video'];
                $channelSlug = $v->channel ? $v->channel->slug : '未知';
                $titleShort = mb_strimwidth($v->name ?: '【无标题】', 0, 32, '...');

                foreach ($item['issues'] as $issue) {
                    $levelTag = match ($issue['level']) {
                        'FATAL'   => '<error>[致命 FATAL]</error>',
                        'ERROR'   => '<error>[错误 ERROR]</error>',
                        'WARNING' => '<comment>[警告 WARN]</comment>',
                        default   => '<info>[提示 INFO]</info>',
                    };

                    $rows[] = [
                        $v->id,
                        $channelSlug,
                        $v->video_code ?: '【空编码】',
                        $titleShort,
                        $levelTag . ' ' . $issue['field'] . ': ' . $issue['message'],
                        $issue['impact'],
                    ];
                }
            }

            $this->table(
                ['ID', '片商', '番号/编码', '视频标题', '缺陷项与说明', '对前端展示的影响'],
                $rows
            );
        }

        // 如果存在演员缺陷，列出详细问题清单
        if (!empty($actorIssues)) {
            $this->newLine();
            $this->error("🚨 发现以下演员数据存在缺陷项：");

            $rows = [];
            foreach ($actorIssues as $item) {
                /** @var Actor $a */
                $a = $item['actor'];
                foreach ($item['issues'] as $issue) {
                    $levelTag = match ($issue['level']) {
                        'FATAL'   => '<error>[致命 FATAL]</error>',
                        'ERROR'   => '<error>[错误 ERROR]</error>',
                        'WARNING' => '<comment>[警告 WARN]</comment>',
                        default   => '<info>[提示 INFO]</info>',
                    };

                    $rows[] = [
                        $a->id,
                        $a->name ?: '【空名字】',
                        $a->slug ?: '【空SLUG】',
                        "{$a->videos_count} 部",
                        $levelTag . ' ' . $issue['field'] . ': ' . $issue['message'],
                        $issue['impact'],
                    ];
                }
            }

            $this->table(
                ['ID', '演员名称', 'Slug', '关联视频数', '缺陷项与说明', '对前端展示的影响'],
                $rows
            );
        }

        // 最终综合安全裁决
        $this->newLine();
        $hasFatalOrError = false;
        foreach (array_merge($videoIssues, $actorIssues) as $entry) {
            foreach ($entry['issues'] as $iss) {
                if (in_array($iss['level'], ['FATAL', 'ERROR'])) {
                    $hasFatalOrError = true;
                    break 2;
                }
            }
        }

        if (!$hasFatalOrError && (empty($videoIssues) && empty($actorIssues))) {
            $this->info("✅【完美通过】所有抽查数据的关键字段、图片地址、关联关系均完整无缺，前端可 100% 安全展示！🎉");
            return self::SUCCESS;
        }

        if (!$hasFatalOrError && !$strict) {
            $this->comment("⚠️【基本合格】未检测到阻断前端的致命或严重错误，仅存在少量次要警告（如个别视频无时长或描述为空），前端整体安全可用。");
            return self::SUCCESS;
        }

        $this->error("❌【存在风险】检测到部分影响前端展示的错误或关键缺失！建议针对上述视频/演员重新执行增量爬虫同步。");
        return self::FAILURE;
    }
}
