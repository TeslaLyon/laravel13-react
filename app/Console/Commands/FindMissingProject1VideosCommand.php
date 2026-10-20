<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Video;
use App\Services\Crawler\Project1VideoCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FindMissingProject1VideosCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'crawler:find-missing-videos
                            {channel=bangbros : 片商 slug (如 bangbros, brazzers 等)}
                            {--limit=100 : 接口分页大小，最大推荐 100}';

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

        if (!$channel) {
            $this->error("❌ 未在数据库找到片商 slug=[{$slug}] 的记录。");
            return self::FAILURE;
        }

        $this->info("🔍 正在加载本地库中 [{$channel->name}] (channel_id={$channel->id}) 的所有现有视频...");

        // 1. 获取本地数据库中已存在的所有 source ID
        $existingSourceIds = [];
        Video::where('channel_id', $channel->id)
            ->select(['id', 'source_uuid', 'slug'])
            ->chunk(2000, function ($videos) use (&$existingSourceIds, $slug) {
                foreach ($videos as $video) {
                    $uuid = $video->source_uuid;
                    // 兼容提取 source_uuid 中的数字 ID，例如 "bangbros:12345:slug" 或 "12345"
                    if (preg_match('/(?:' . preg_quote($slug, '/') . ':)?(\d+)/', $uuid, $matches)) {
                        $existingSourceIds[$matches[1]] = true;
                    } elseif (preg_match('/-(\d+)-/', $video->slug, $matches)) {
                        $existingSourceIds[$matches[1]] = true;
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

                        $reason = match (true) {
                            $type !== 'scene'                                         => "非正片 (type='{$type}')",
                            $orientation === 'gay'                                    => '同性题材 (orientation=gay)',
                            in_array($orientation, ['trans', 'transgender'], true)    => '变性题材 (orientation=trans)',
                            $isTransActor                                             => '演员包含变性模特',
                            default                                                   => '历史队列遗漏或去重合并',
                        };

                        $missingItems[] = [
                            'id'          => $id,
                            'title'       => $item['title'] ?? '(无标题)',
                            'type'        => $type,
                            'orientation' => $orientation ?: 'normal',
                            'reason'      => $reason,
                            'released'    => $item['dateReleased'] ?? '-',
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

        return self::SUCCESS;
    }
}

