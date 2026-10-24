<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Video;
use App\Services\Crawler\Project1VideoCrawlerService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ReflectionMethod;

class FindMissingProject1VideosCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'crawler:find-missing-videos
                            {channel=bangbros : 片商 slug (如 bangbros, brazzers 等)}
                            {--limit=100 : 接口分页大小，最大推荐 100}
                            {--fix : 自动尝试将缺失的有效正片补写入库}';

    /**
     * The console command description.
     */
    protected $description = '排查 Project1 片商接口 total 数量与本地数据库数量不一致的缺失视频明细及原因';

    /**
     * Execute the console command.
     */
    public function handle(Project1VideoCrawlerService $crawlerService): int
    {
        $slug = (string) $this->argument('channel');
        $channel = Channel::where('slug', $slug)->first();

        if (!$channel && Project1VideoCrawlerService::isSupported($slug)) {
            $channel = $crawlerService->ensureChannel($slug);
        }

        if (!$channel) {
            $this->error("❌ 未在数据库找到片商 slug=[{$slug}] 的记录。");
            return self::FAILURE;
        }

        $this->info("🔍 正在加载本地库中 [{$channel->name}] (channel_id={$channel->id}) 的所有现有视频...");

        // 1. 获取本地数据库中已存在的所有 source ID 及全局 video_code 映射
        $existingSourceIds = [];
        $existingVideoCodes = [];
        Video::select(['id', 'source_uuid', 'slug', 'video_code', 'channel_id'])
            ->chunk(2000, function ($videos) use (&$existingSourceIds, &$existingVideoCodes, $slug, $channel) {
                foreach ($videos as $video) {
                    if (!empty($video->video_code)) {
                        $existingVideoCodes[$video->video_code] = (int) $video->channel_id;
                    }
                    if ((int) $video->channel_id === (int) $channel->id) {
                        $uuid = $video->source_uuid;
                        if (preg_match('/(?:' . preg_quote($slug, '/') . ':)?(\d+)/', $uuid, $matches)) {
                            $existingSourceIds[$matches[1]] = true;
                        } elseif (preg_match('/-(\d+)-/', $video->slug, $matches)) {
                            $existingSourceIds[$matches[1]] = true;
                        }
                    }
                }
            });

        $localCount = count($existingSourceIds);
        $this->info("📦 本地已存在有效视频唯一 ID 总数: {$localCount} 个。");

        // 2. 获取有效 Token
        $token = $crawlerService->resolveChannelToken($channel);
        if (empty($token)) {
            $this->error("❌ 无法获取片商 [{$channel->slug}] 的有效 Token，请检查官网配置。");
            return self::FAILURE;
        }

        // 3. 探查接口第一页及总数量
        $limit = max(10, min(100, (int) $this->option('limit')));
        $origin = rtrim($channel->official_website_url ?: 'https://www.project1service.com', '/');

        $this->info("🌐 正在连接 Project1 源站 API (Limit: {$limit})...");

        $firstRes = Http::withHeaders([
            'user-agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'referer'         => $origin . '/',
            'origin'          => $origin,
            'instance'        => $token,
            'accept'          => 'application/json, text/plain, */*',
            'authority'       => 'site-api.project1service.com',
        ])->timeout(30)->get('https://site-api.project1service.com/v2/releases', [
            'adaptiveStreamingOnly' => 'false',
            'orderBy'               => '-dateReleased',
            'type'                  => 'scene',
            'limit'                 => $limit,
            'offset'                => 0,
        ]);

        if (!$firstRes->successful()) {
            $this->error("❌ API 请求失败 [HTTP {$firstRes->status()}]: " . Str::limit($firstRes->body(), 200));
            return self::FAILURE;
        }

        $firstData = $firstRes->json();
        $apiTotal = $firstData['meta']['total'] ?? null;
        if ($apiTotal === null) {
            $this->error("❌ API 返回结构缺少 meta.total。");
            return self::FAILURE;
        }

        $totalPages = (int) ceil($apiTotal / $limit);
        $diffCount = $apiTotal - $localCount;
        $this->info("🎯 源站接口 meta.total: {$apiTotal} 部，本地库内: {$localCount} 部，预期差额: {$diffCount} 部 (总共 {$totalPages} 页)。");
        $this->line("⏳ 正在快速比对源站与本地库差异，请稍候...\n");

        $bar = $this->output->createProgressBar($totalPages);
        $bar->start();

        $missingItems = [];

        for ($page = 1; $page <= $totalPages; $page++) {
            $offset = ($page - 1) * $limit;

            $res = Http::withHeaders([
                'user-agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'referer'         => $origin . '/',
                'origin'          => $origin,
                'instance'        => $token,
                'accept'          => 'application/json, text/plain, */*',
                'authority'       => 'site-api.project1service.com',
            ])->timeout(30)->get('https://site-api.project1service.com/v2/releases', [
                'adaptiveStreamingOnly' => 'false',
                'orderBy'               => '-dateReleased',
                'type'                  => 'scene',
                'limit'                 => $limit,
                'offset'                => $offset,
            ]);

            if ($res->successful()) {
                $data = $res->json();
                $items = $data['result'] ?? [];

                foreach ($items as $item) {
                    $id = (string) ($item['id'] ?? '');
                    if ($id === '') {
                        continue;
                    }

                    if (!isset($existingSourceIds[$id])) {
                        // 分析未入库的原因
                        $type = (string) ($item['type'] ?? '');
                        $orientation = strtolower((string) ($item['sexualOrientation'] ?? ''));

                        $isTransActor = false;
                        if (!empty($item['actors']) && is_array($item['actors'])) {
                            foreach ($item['actors'] as $actor) {
                                if (is_array($actor) && strtolower((string) ($actor['gender'] ?? '')) === 'trans') {
                                    $isTransActor = true;
                                    break;
                                }
                            }
                        }

                        $videoCodeCollision = false;
                        $collisionDesc = '';
                        // 推导该视频的预期 video_code 检查是否有全局唯一冲突
                        try {
                            $releaseAt = Carbon::parse($item['dateReleased'] ?? null)->setTimezone('Asia/Shanghai')->toDateTimeString();
                            $femaleActors = [];
                            foreach ($item['actors'] ?? [] as $actor) {
                                if (is_array($actor)) {
                                    $femaleActors[] = [
                                        'name'   => $actor['name'] ?? '',
                                        'gender' => match ($actor['gender'] ?? '') { 'male' => 1, 'female' => 2, 'trans' => 3, default => 0 },
                                    ];
                                }
                            }
                            $sourceSlug = Str::slug($item['title'] ?? '');
                            $method = new ReflectionMethod($crawlerService, 'handleVideoCode');
                            $expectedCode = $method->invoke($crawlerService, $item, $femaleActors, $sourceSlug, $releaseAt);

                            if (isset($existingVideoCodes[$expectedCode])) {
                                $codeOwnerChannel = $existingVideoCodes[$expectedCode];
                                if ($codeOwnerChannel === (int) $channel->id) {
                                    $videoCodeCollision = true;
                                    $collisionDesc = '源站同一视频重复发布/多集合收录 (编号已存在)';
                                } else {
                                    $videoCodeCollision = true;
                                    $collisionDesc = "跨片商编号冲突 (已被片商ID:{$codeOwnerChannel}收录)";
                                }
                            }
                        } catch (\Throwable $e) {
                            // 忽略推导异常
                        }

                        $reason = match (true) {
                            $type !== 'scene'                                         => "非正片 (type='{$type}')",
                            $orientation === 'gay'                                    => '同性题材 (orientation=gay)',
                            in_array($orientation, ['trans', 'transgender'], true)    => '变性题材 (orientation=trans)',
                            $isTransActor                                             => '演员包含变性模特',
                            $videoCodeCollision                                       => $collisionDesc,
                            default                                                   => '历史队列遗漏',
                        };

                        $missingItems[] = [
                            'id'          => $id,
                            'title'       => $item['title'] ?? '(无标题)',
                            'type'        => $type,
                            'orientation' => $orientation ?: 'normal',
                            'reason'      => $reason,
                            'released'    => $item['dateReleased'] ?? '-',
                            'raw_item'    => $item,
                        ];
                    }
                }
            }

            $bar->advance();
            // 轻量防抖 50ms
            usleep(50000);
        }

        $bar->finish();
        $this->line("\n");

        if (empty($missingItems)) {
            $this->info("🎉 未发现遗漏数据！可能是去重计算或接口分页边缘变动。");
            return self::SUCCESS;
        }

        $this->warn("📋 找到 " . count($missingItems) . " 部未入库的视频清单及详细原因：");

        $rows = array_map(function ($m) {
            return [
                $m['id'],
                Str::limit($m['title'], 45),
                $m['type'],
                $m['orientation'],
                $m['reason'],
                $m['released'],
            ];
        }, $missingItems);

        $this->table(['源站ID', '视频标题 (Title)', '类型 (Type)', '性取向', '未入库原因分析', '发布时间'], $rows);

        // 如果开启了 --fix 模式，立即自动补录
        if ($this->option('fix')) {
            $this->info("\n🛠️ 已开启 --fix 模式，正在尝试自动补录这 " . count($missingItems) . " 部视频...");
            $fixedCount = 0;
            $failedCount = 0;

            foreach ($missingItems as $m) {
                $rawItem = $m['raw_item'];
                $id = $m['id'];
                $title = $m['title'];

                $this->line("👉 正在尝试补录 [{$id}] {$title}...");
                try {
                    $reflection = new ReflectionMethod($crawlerService, 'saveVideoItem');
                    $savedVideo = $reflection->invoke($crawlerService, $channel, $rawItem);
                    $this->info("   ✅ 补录成功！新建视频 ID: {$savedVideo->id}, 番号: {$savedVideo->video_code}");
                    $fixedCount++;
                } catch (\Throwable $e) {
                    $this->error("   ❌ 补录失败，详细异常: " . $e->getMessage());
                    $failedCount++;
                }
            }

            $this->info("\n🎉 补录执行完毕：成功 {$fixedCount} 部，失败 {$failedCount} 部。");
            $newTotal = Video::where('channel_id', $channel->id)->count();
            $channel->update(['video_num' => $newTotal]);
            $this->info("📊 本地库内最新视频数量已更新为: {$newTotal} 部。");
        } else {
            $this->line("\n💡 提示：若需将以上未入库的正片视频自动尝试补写入库，可追加 --fix 执行：");
            $this->comment("   php artisan crawler:find-missing-videos {$slug} --fix");
        }

        return self::SUCCESS;
    }
}

