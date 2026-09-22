<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\Translation\GeminiTranslationService;
use Illuminate\Console\Command;

class TranslateVideoTitlesCommand extends Command
{
    /**
     * 控制台命令签名
     *
     * @var string
     */
    protected $signature = 'videos:translate-titles
                            {--limit=60 : 本次任务最多翻译的视频记录数}
                            {--batch-size=15 : 单次请求 Gemini 合并翻译的视频数量}
                            {--sleep=4 : 批次之间的休眠等待秒数（默认 4 秒，严格遵循 15 RPM 限制）}
                            {--force : 强制重新翻译已有中文标题的视频}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '使用 Gemini AI 自动翻译待完善的视频中文标题 (name_zh)';

    /**
     * 执行控制台命令
     */
    public function handle(GeminiTranslationService $translator): int
    {
        if (!$translator->isConfigured()) {
            $this->error('❌ 未配置 GEMINI_API_KEY，请在 .env 文件中配置 GEMINI_API_KEY 后再运行此任务。');
            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $batchSize = max(1, min(30, (int) $this->option('batch-size')));
        $sleepSeconds = max(0, (int) $this->option('sleep'));
        $force = (bool) $this->option('force');

        $this->info("🔍 正在检索待翻译视频 (上限: {$limit} 条, 每批: {$batchSize} 条, 批次间隔: {$sleepSeconds} 秒)...");

        $query = Video::query();
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('name_zh')->orWhere('name_zh', '');
            });
        }

        $videos = $query->orderBy('id', 'desc')
            ->limit($limit)
            ->get(['id', 'name', 'name_zh']);

        $totalFound = $videos->count();
        if ($totalFound === 0) {
            $this->info('🎉 没有需要翻译的视频数据！');
            return self::SUCCESS;
        }

        $this->info("📦 共找到 {$totalFound} 条视频等待翻译，开始分批向 Gemini AI 发送翻译请求...");

        $chunks = $videos->chunk($batchSize);
        $totalChunks = $chunks->count();
        $processedCount = 0;
        $successCount = 0;
        $failedCount = 0;

        foreach ($chunks as $index => $chunk) {
            $chunkIndex = $index + 1;
            $items = $chunk->pluck('name', 'id')->toArray();

            $this->line("⏳ [批次 {$chunkIndex}/{$totalChunks}] 正在请求 Gemini 翻译 " . count($items) . " 条标题...");

            $translations = $translator->translateBatch($items);

            foreach ($chunk as $video) {
                $processedCount++;
                if (isset($translations[$video->id]) && filled($translations[$video->id])) {
                    $nameZh = $translations[$video->id];
                    Video::where('id', $video->id)->update(['name_zh' => $nameZh]);
                    $successCount++;
                } else {
                    $failedCount++;
                }
            }

            $batchSuccess = count($translations);
            $batchTotal = count($items);
            $this->line("   └─ 批次完成: 成功 {$batchSuccess}/{$batchTotal} 条");

            // 若非最后一批且配置了休眠等待，则进行速率冷却，确保不超过 Gemini 15 RPM 限制
            if ($chunkIndex < $totalChunks && $sleepSeconds > 0) {
                $this->comment("   ⏱ 冷却休眠 {$sleepSeconds} 秒 (防触发 15 RPM 频控)...");
                sleep($sleepSeconds);
            }
        }

        $this->newLine();
        $this->info('✅ 视频标题翻译任务执行完成！');
        $this->table(
            ['待处理总数', '已处理', '成功更新', '未成功/未返回'],
            [[$totalFound, $processedCount, $successCount, $failedCount]]
        );

        return self::SUCCESS;
    }
}

