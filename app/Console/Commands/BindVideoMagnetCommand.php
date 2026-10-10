<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Video;
use App\Models\VideoDownload;
use Illuminate\Support\Facades\DB;

class BindVideoMagnetCommand extends Command
{
    /**
     * 命令标识与参数
     */
    protected $signature = 'video:bind-magnet
                            {video? : 目标视频的 ID、Slug 或视频编码 (不填将进入交互式搜索/输入)}
                            {--magnet=* : 指定磁力链接 (可多次传递，未传递时默认使用 High Gear 两条磁力)}
                            {--dry-run : 仅预检匹配，不实际写入数据库}';

    /**
     * 命令描述
     */
    protected $description = '手动将指定的磁力链接绑定并关联至目标视频 (Video)';

    /**
     * 默认磁力链接 (第 834、835 行 High Gear)
     */
    protected array $defaultMagnets = [
        'magnet:?xt=urn:btih:9b7406ec07de8022005970f76906d51a274e0690&dn=BlackedRaw.22.06.27.High.Gear.XXX.1080p.MP4-NBQ',
        'magnet:?xt=urn:btih:eba4661983a4f2b1a9ade564fe0123921f6ba68c&dn=BlackedRaw.22.06.27.High.Gear.XXX.SD.MP4-KLEENEX',
    ];

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $this->info("======================================================");
        $this->info("🔗 磁力链接手动关联绑定工具 (Video Magnet Binder)");
        $this->info("======================================================");

        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn("⚠️  当前处于【--dry-run 预检模式】，不会对数据库进行任何写入操作！");
        }

        // 1. 查找或获取目标视频
        $video = $this->resolveTargetVideo();
        if (!$video) {
            $this->error("❌ 未能找到目标视频，绑定已终止。");
            return self::FAILURE;
        }

        $this->info("🎯 目标视频信息:");
        $this->table(
            ['字段', '值'],
            [
                ['ID', $video->id],
                ['标题 (Name)', $video->name],
                ['中文译名 (Name Zh)', $video->name_zh ?: '-'],
                ['视频编码 (Code)', $video->video_code],
                ['Slug', $video->slug],
                ['片商 (Channel ID)', $video->channel_id],
                ['发布日期 (Release At)', $video->release_at ? substr((string) $video->release_at, 0, 10) : '-'],
                ['当前下载标记 (has_downloads)', $video->has_downloads ? '已拥有 (true)' : '未拥有 (false)'],
            ]
        );

        // 2. 准备要绑定的磁力链接列表
        $magnetInputs = $this->option('magnet');
        if (empty($magnetInputs)) {
            $this->comment("ℹ️  未指定 --magnet 参数，使用默认的两条 High Gear 磁力链接：");
            $magnetsToBind = $this->defaultMagnets;
        } else {
            $magnetsToBind = $magnetInputs;
        }

        $summaryTable = [];
        $recordsToInsert = [];

        // 3. 解析磁力链接
        foreach ($magnetsToBind as $index => $magnetLine) {
            $magnetLine = trim($magnetLine);
            if (empty($magnetLine)) {
                continue;
            }

            $parsed = $this->parseMagnet($magnetLine);
            if (!$parsed) {
                $this->error("❌ [第 " . ($index + 1) . " 条] 无法解析磁力链接格式或缺少 dn/xt 参数: {$magnetLine}");
                continue;
            }

            $summaryTable[] = [
                '清晰度'   => $parsed['resolution'] ?: '未知',
                '文件名 (dn)' => $parsed['dn'],
                '特征哈希 (Hash)' => $parsed['hash'],
                '排序权重' => $parsed['sort_order'],
            ];

            $recordsToInsert[] = [
                'video_id'         => $video->id,
                'user_id'          => null,
                'title'            => $parsed['dn'],
                'type'             => 'magnet',
                'cost_type'        => 'free',
                'resolution'       => $parsed['resolution'],
                'price'            => null,
                'link'             => $magnetLine,
                'hash'             => $parsed['hash'],
                'file_size'        => null,
                'extraction_code'  => null,
                'archive_password' => null,
                'description'      => null,
                'status'           => 1,
                'sort_order'       => $parsed['sort_order'],
            ];
        }

        if (empty($recordsToInsert)) {
            $this->error("❌ 没有可用的有效磁力链接，绑定取消。");
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("📋 待绑定磁力明细:");
        $this->table(['清晰度', '文件名 (dn)', '特征哈希 (Hash)', '排序权重'], $summaryTable);

        // 4. 用户二次确认 (非交互模式或传参确认)
        if (!$this->confirm("确认将上述 " . count($recordsToInsert) . " 条磁力链接绑定至该视频吗?", true)) {
            $this->warn("已取消绑定操作。");
            return self::SUCCESS;
        }

        if ($isDryRun) {
            $this->info("✅ [Dry-Run] 预检成功，模拟写入完成 (无实际修改)。");
            return self::SUCCESS;
        }

        // 5. 写入数据库事务
        DB::transaction(function () use ($recordsToInsert, $video) {
            foreach ($recordsToInsert as $payload) {
                $existing = VideoDownload::where('hash', $payload['hash'])->first();
                if ($existing) {
                    $existing->update($payload);
                    $this->line("  🔄 已更新已有磁力记录 (ID: {$existing->id}, Hash: {$payload['hash']})");
                } else {
                    $created = VideoDownload::create($payload);
                    $this->line("  ✨ 已成功创建新下载记录 (ID: {$created->id}, Hash: {$payload['hash']})");
                }
            }

            // 更新视频的 has_downloads 标记为 true
            $video->update(['has_downloads' => true]);
            $video->has_downloads = true;
        });

        $this->newLine();
        $this->info("🎉 绑定成功！已将 " . count($recordsToInsert) . " 条磁力关联至视频 [ID: {$video->id}] 并已将视频 has_downloads 置为 true。");
        $this->info("======================================================");

        return self::SUCCESS;
    }

    /**
     * 定位目标视频 (支持 ID、视频编码、Slug 或关键字搜索)
     */
    protected function resolveTargetVideo(): ?Video
    {
        $input = $this->argument('video');

        if (empty($input)) {
            $input = $this->ask("请输入要绑定的目标视频 ID (或视频编码/标题关键字)");
        }

        $input = trim((string) $input);
        if (empty($input)) {
            return null;
        }

        // 1. 若纯数字，按主键 ID 查找
        if (is_numeric($input)) {
            $video = Video::find((int) $input);
            if ($video) {
                return $video;
            }
        }

        // 2. 按编码完全匹配
        $video = Video::where('video_code', $input)->first();
        if ($video) {
            return $video;
        }

        // 3. 按 Slug 完全匹配
        $video = Video::where('slug', $input)->first();
        if ($video) {
            return $video;
        }

        // 4. 模糊搜索 (片名/编码)
        $candidates = Video::where(function ($q) use ($input) {
            $q->where('name', 'ILIKE', "%{$input}%")
              ->orWhere('video_code', 'ILIKE', "%{$input}%")
              ->orWhere('name_zh', 'ILIKE', "%{$input}%");
        })->take(5)->get();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        if ($candidates->count() > 1) {
            $choices = [];
            foreach ($candidates as $c) {
                $choices[$c->id] = "ID: {$c->id} | {$c->name} | 编码: {$c->video_code} | 发布: " . substr((string) $c->release_at, 0, 10);
            }
            $selectedId = $this->choice("找到多部相关视频，请选择目标视频:", $choices);
            return $candidates->firstWhere('id', (int) $selectedId);
        }

        return null;
    }

    /**
     * 解析单个磁力链接
     */
    protected function parseMagnet(string $line): ?array
    {
        // 1. 提取 hash (BTIH)
        if (!preg_match('/xt=urn:btih:([a-zA-Z0-9]+)/i', $line, $hashMatch)) {
            return null;
        }
        $hash = strtolower($hashMatch[1]);

        // 2. 提取 dn (Display Name)
        $dn = null;
        if (preg_match('/dn=([^&]+)/i', $line, $dnMatch)) {
            $dn = urldecode($dnMatch[1]);
        } else {
            $dn = "Magnet." . substr($hash, 0, 10);
        }

        // 3. 清晰度与排序权重判断
        $resolution = null;
        $sortOrder = 0;
        if (preg_match('/2160p|4k|uhd/i', $dn)) {
            $resolution = '4K';
            $sortOrder = 10;
        } elseif (preg_match('/1080p/i', $dn)) {
            $resolution = '1080P';
            $sortOrder = 5;
        } elseif (preg_match('/720p/i', $dn)) {
            $resolution = '720P';
            $sortOrder = 3;
        } elseif (preg_match('/sd|480p|540p/i', $dn)) {
            $resolution = 'SD';
            $sortOrder = 1;
        }

        return [
            'hash'       => $hash,
            'dn'         => $dn,
            'resolution' => $resolution,
            'sort_order' => $sortOrder,
        ];
    }
}

