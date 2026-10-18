<?php

namespace App\Console\Commands\Crawler;

use App\Models\Actor;
use App\Services\Crawler\TpdbApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FixActorTpdbImagesCommand extends Command
{
    protected $signature = 'crawler:fix-actor-tpdb-images 
                            {--actor= : 指定修复单个演员名称或 slug}
                            {--force : 强制重新转码压缩并覆盖已存在的头像文件}
                            {--dry-run : 仅预览，不实际修改数据库和上传 R2}';

    protected $description = '清理并纠正演员图片数据：将 avatar 纠正为 TPDB 的 face (AVIF格式且严格<=20KB)，将 booty_img 纠正为 TPDB 的 image (外部原链不上传R2)';

    public function handle(TpdbApiService $tpdbService): int
    {
        $this->info("==================================================");
        $this->info("🧹 启动演员头像 (face <=20KB) 与封面图 (image) 数据清洗纠正任务");
        $this->info("==================================================");

        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn("⚠️ 当前为 DRY-RUN 预览模式，不会修改数据库和上传 R2！");
        }

        $specificActor = $this->option('actor');
        $query = Actor::query();
        if ($specificActor) {
            $query->where('name', $specificActor)->orWhere('slug', $specificActor);
        } else {
            // 重点检查：booty_img 为空，或者 avatar 包含 images/actors/ 的演员
            $query->orderBy('id', 'desc');
        }

        $actors = $query->get();
        $this->info("📊 待检索与检查演员总数: " . $actors->count() . " 位");

        $updatedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($actors as $actor) {
            $this->line("--------------------------------------------------");
            $this->line("👤 检查演员: [{$actor->name}] (ID: {$actor->id}, Slug: {$actor->slug})");

            try {
                $performer = $tpdbService->searchPerformer($actor->name);
                if (!$performer) {
                    $this->comment("   ⏩ TPDB 未检索到该演员，跳过");
                    $skippedCount++;
                    continue;
                }

                $faceUrl = $performer['face'] ?? null;
                $imageUrl = $performer['image'] ?? null;

                $needsUpdate = false;
                $updates = [];
                $oldAvatar = $actor->avatar;

                // 1. 检查 avatar：是否需要纠正为 face，或者当前头像是否超过 20KB
                if (!empty($faceUrl)) {
                    $cleanFaceUrl = explode('?', $faceUrl)[0];
                    $expectedFaceSubPath = "images/actors/{$actor->slug}/" . sha1($cleanFaceUrl) . ".avif";

                    $r2Size = 0;
                    if (!empty($actor->avatar) && Storage::disk('r2')->exists($actor->avatar)) {
                        try {
                            $r2Size = Storage::disk('r2')->size($actor->avatar);
                        } catch (Throwable) {}
                    }

                    $isPathMismatched = ($actor->avatar !== $expectedFaceSubPath);
                    $isOverSized = ($r2Size > 20 * 1024); // 超过 20KB 强制重新压缩

                    if ($isPathMismatched || $isOverSized || $this->option('force')) {
                        $reason = $isPathMismatched ? "路径非规范" : ($isOverSized ? "体积超标 (" . round($r2Size / 1024, 2) . " KB > 20 KB)" : "强制重新优化");
                        $this->info("   🎯 发现头像需要优化纠正 ({$reason}):");
                        $this->line("      - 旧 Avatar: " . ($actor->avatar ?: '空') . ($r2Size > 0 ? " [" . round($r2Size / 1024, 2) . " KB]" : ""));
                        $this->line("      - 目标 R2 路径: {$expectedFaceSubPath}");

                        if (!$dryRun) {
                            $newAvatarPath = $tpdbService->storeAvatarAsAvif($actor->slug, $faceUrl, true);
                            if ($newAvatarPath) {
                                $updates['avatar'] = $newAvatarPath;
                                $needsUpdate = true;

                                // 如果旧头像是不同哈希的 AVIF，清理 R2 上的旧图
                                if (!empty($oldAvatar) && $oldAvatar !== $newAvatarPath && str_starts_with($oldAvatar, 'images/actors/')) {
                                    try {
                                        if (Storage::disk('r2')->exists($oldAvatar)) {
                                            Storage::disk('r2')->delete($oldAvatar);
                                            $this->line("      🗑️ 已清理 R2 旧头像文件: {$oldAvatar}");
                                        }
                                    } catch (Throwable $e) {
                                        $this->warn("      ⚠️ 清理 R2 旧头像失败: " . $e->getMessage());
                                    }
                                }
                            }
                        } else {
                            $needsUpdate = true;
                        }
                    }
                }

                // 2. 检查 booty_img：是否需要补充或纠正为 image
                if (!empty($imageUrl)) {
                    $currentBooty = $actor->booty_img;
                    $currentSrc = is_array($currentBooty) ? ($currentBooty[0]['src'] ?? null) : $currentBooty;

                    if ($currentSrc !== $imageUrl) {
                        $this->info("   🖼️ 发现封面图需要纠正为 image 字段 (外部直链，不上传 R2):");
                        $this->line("      - 旧 BootyImg: " . ($currentSrc ?: '空'));
                        $this->line("      - 新 Image 原链: {$imageUrl}");

                        if (!$dryRun) {
                            $updates['booty_img'] = $tpdbService->buildBootyImg($imageUrl);
                            $needsUpdate = true;
                        } else {
                            $needsUpdate = true;
                        }
                    }
                }

                // 3. 执行更新
                if ($needsUpdate) {
                    if (!$dryRun && !empty($updates)) {
                        $actor->update($updates);
                        $this->info("   ✅ 演员数据纠正完成！");
                    } else {
                        $this->comment("   🔍 [DRY-RUN] 需要纠正，预览跳过执行");
                    }
                    $updatedCount++;
                } else {
                    $this->line("   👌 演员数据规范一致，无需更新");
                    $skippedCount++;
                }

            } catch (Throwable $e) {
                $this->error("   ❌ 处理异常: " . $e->getMessage());
                $failedCount++;
            }
        }

        $this->newLine();
        $this->info("==================================================");
        $this->info("🎉 数据清洗与纠正任务完成！");
        $this->info("   - 已纠正更新: {$updatedCount}");
        $this->info("   - 无需更新/跳过: {$skippedCount}");
        $this->info("   - 失败异常: {$failedCount}");
        $this->info("==================================================");

        return 0;
    }
}

