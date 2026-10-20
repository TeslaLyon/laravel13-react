<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Video;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendCrawlerDailySummaryCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'crawler:send-daily-summary
                            {--date= : 指定统计日期 (格式: YYYY-MM-DD)，默认为当天}
                            {--channel= : 指定单个片商 slug，未指定则汇总全部已接入片商}
                            {--dry-run : 仅在控制台预览统计结果，不执行 Telegram 推送}';

    /**
     * The console command description.
     */
    protected $description = '汇总当日爬虫抓取数据，并自动向 Telegram 机器人/群组推送日报';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService): int
    {
        $timezone = 'Asia/Shanghai';
        $dateStr = $this->option('date');
        $targetDate = $dateStr ? Carbon::parse($dateStr, $timezone) : Carbon::today($timezone);
        $startOfDay = $targetDate->copy()->startOfDay();
        $endOfDay = $targetDate->copy()->endOfDay();

        $channelSlug = $this->option('channel');
        $isDryRun = (bool) $this->option('dry-run');

        $this->info("📊 正在统计 [{$targetDate->toDateString()}] 的视频抓取数据汇总...");

        // 获取目标片商（优先筛选有爬虫配置的片商）
        $channelQuery = Channel::query();
        if ($channelSlug) {
            $channelQuery->where('slug', $channelSlug);
        } else {
            $channelQuery->where('data_crawl_type', '>', 0);
        }
        $channels = $channelQuery->orderBy('data_crawl_type')->orderBy('id')->get();

        if ($channels->isEmpty()) {
            $this->warn("⚠️ 未找到匹配的片商记录。");
            return self::SUCCESS;
        }

        $totalNewCount = 0;
        $totalUpdatedCount = 0;
        $channelStats = [];
        $latestNewSamples = [];

        foreach ($channels as $channel) {
            $newCount = Video::where('channel_id', $channel->id)
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->count();

            $updatedCount = Video::where('channel_id', $channel->id)
                ->whereBetween('updated_at', [$startOfDay, $endOfDay])
                ->where('created_at', '<', $startOfDay)
                ->count();

            $totalVideos = Video::where('channel_id', $channel->id)->count();

            // 采样今日最新入库的 1 部视频
            $sample = Video::where('channel_id', $channel->id)
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->latest('id')
                ->first(['id', 'name', 'video_code', 'release_at']);

            $channelStats[] = [
                'name'         => $channel->name,
                'slug'         => $channel->slug,
                'crawl_type'   => $channel->data_crawl_type,
                'new_count'    => $newCount,
                'update_count' => $updatedCount,
                'total_videos' => $totalVideos,
                'sample'       => $sample,
            ];

            $totalNewCount += $newCount;
            $totalUpdatedCount += $updatedCount;

            if ($sample) {
                $latestNewSamples[] = [
                    'channel'    => $channel->name,
                    'code'       => $sample->video_code ?: "#{$sample->id}",
                    'name'       => $sample->name,
                    'release_at' => $sample->release_at ? Carbon::parse($sample->release_at)->format('Y-m-d') : '-',
                ];
            }
        }

        // 1. 控制台终端表格输出
        $tableRows = array_map(function ($s) {
            return [
                $s['name'],
                $s['slug'],
                $s['new_count'] > 0 ? "<fg=green>+{$s['new_count']}</>" : "0",
                $s['update_count'] > 0 ? "<fg=yellow>+{$s['update_count']}</>" : "0",
                number_format($s['total_videos']),
            ];
        }, $channelStats);

        $this->table(['片商名称', 'Slug', '今日新增', '今日更新', '库内总数'], $tableRows);
        $this->info("📈 总计: 今日新增 {$totalNewCount} 部，更新 {$totalUpdatedCount} 部。");

        // 2. 构造 Telegram HTML 消息正文
        $reportDateStr = $targetDate->toDateString();
        $generatedTimeStr = now($timezone)->format('H:i:s');

        $lines = [
            "🤖 <b>每日爬虫抓取汇总日报</b>",
            "━━━━━━━━━━━━━━━━━━━━",
            "📅 <b>统计日期</b>: <code>{$reportDateStr}</code>",
            "⏱️ <b>生成时间</b>: <code>{$generatedTimeStr}</code> (UTC+8)",
            "",
            "📈 <b>当日数据大盘</b>",
            "• ✨ <b>新增视频</b>: <b>{$totalNewCount}</b> 部",
            "• 🔄 <b>信息更新</b>: <b>{$totalUpdatedCount}</b> 部",
            "",
            "🏢 <b>各片商采集明细</b>",
        ];

        foreach ($channelStats as $stat) {
            $badge = $stat['new_count'] > 0 ? "🟢" : "⚪";
            $newStr = $stat['new_count'] > 0 ? "新增 <b>+{$stat['new_count']}</b>" : "暂无新增";
            $updateStr = $stat['update_count'] > 0 ? "更新 <b>{$stat['update_count']}</b>" : "";
            $actionDesc = array_filter([$newStr, $updateStr]);
            $actionText = implode(', ', $actionDesc);
            $totalFormatted = number_format($stat['total_videos']);

            $lines[] = "{$badge} <b>{$stat['name']}</b>: {$actionText} (总库: {$totalFormatted})";
        }

        if (!empty($latestNewSamples)) {
            $lines[] = "";
            $lines[] = "🎬 <b>今日最新入库样片（前 " . min(count($latestNewSamples), 5) . " 部）:</b>";
            $sampleSlice = array_slice($latestNewSamples, 0, 5);
            foreach ($sampleSlice as $item) {
                $code = htmlspecialchars($item['code'], ENT_QUOTES);
                $title = htmlspecialchars(mb_strimwidth($item['name'], 0, 36, '...'), ENT_QUOTES);
                $lines[] = "• [{$item['channel']}] <code>{$code}</code> {$title} (<i>{$item['release_at']}</i>)";
            }
        }

        $lines[] = "";
        $lines[] = "🛡️ <i>安全过滤已生效：自动剔除非正片、Gay 及 Trans（变性）题材。</i>";

        $htmlMessage = implode("\n", $lines);

        // 3. 执行 Telegram 发送
        if ($isDryRun) {
            $this->line("\n<fg=yellow>【--dry-run 模式已开启，Telegram 消息预览如下】</>");
            $this->line(strip_tags($htmlMessage));
            return self::SUCCESS;
        }

        $this->info("📲 正在发送汇总日报至 Telegram...");
        $sent = $telegramService->sendMessage($htmlMessage);

        if ($sent) {
            $this->info("✅ Telegram 汇总日报发送成功！");
        } else {
            $this->warn("⚠️ Telegram 发送未成功（请检查 TELEGRAM_BOT_TOKEN 与 TELEGRAM_CHAT_ID 配置或网络）。");
        }

        return self::SUCCESS;
    }
}

