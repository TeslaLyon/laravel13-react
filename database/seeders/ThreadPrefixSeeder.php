<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Thread;
use App\Models\ThreadPrefix;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ThreadPrefixSeeder extends Seeder
{
    /**
     * 运行彩色前缀模拟数据填充
     */
    public function run(): void
    {
        DB::transaction(function () {
            // ─────────────────────────────────────────────────────────────
            // 1. 初始化彩色前缀标签字典 (对标截屏配色)
            // ─────────────────────────────────────────────────────────────
            $prefixes = [
                [
                    'name' => 'Verified',
                    'slug' => 'verified',
                    'bg_color' => '#6f42c1', // 紫色
                    'text_color' => '#ffffff',
                    'description' => '官方认证或已核实的优质内容',
                    'display_order' => 1,
                    'is_active' => true,
                ],
                [
                    'name' => 'Request',
                    'slug' => 'request',
                    'bg_color' => '#0284c7', // 天蓝色
                    'text_color' => '#ffffff',
                    'description' => '求助、资源求购与需求征集',
                    'display_order' => 2,
                    'is_active' => true,
                ],
                [
                    'name' => 'OnlyFans',
                    'slug' => 'onlyfans',
                    'bg_color' => '#00aff0', // OnlyFans 经典亮蓝
                    'text_color' => '#ffffff',
                    'description' => 'OnlyFans 创作者专区与相关讨论',
                    'display_order' => 3,
                    'is_active' => true,
                ],
                [
                    'name' => 'Patreon',
                    'slug' => 'patreon',
                    'bg_color' => '#ff424d', // Patreon 珊瑚红
                    'text_color' => '#ffffff',
                    'description' => 'Patreon 赞助订阅专栏',
                    'display_order' => 4,
                    'is_active' => true,
                ],
                [
                    'name' => 'Cosplay',
                    'slug' => 'cosplay',
                    'bg_color' => '#10b981', // 翡翠绿
                    'text_color' => '#ffffff',
                    'description' => '二次元与角色扮演 Cosplay 摄影分享',
                    'display_order' => 5,
                    'is_active' => true,
                ],
                [
                    'name' => 'ManyVids',
                    'slug' => 'manyvids',
                    'bg_color' => '#3b82f6', // 经典蓝
                    'text_color' => '#ffffff',
                    'description' => 'ManyVids 平台原创内容',
                    'display_order' => 6,
                    'is_active' => true,
                ],
                [
                    'name' => 'Teen',
                    'slug' => 'teen',
                    'bg_color' => '#ec4899', // 亮粉色
                    'text_color' => '#ffffff',
                    'description' => '青春元气风格分类',
                    'display_order' => 7,
                    'is_active' => true,
                ],
                [
                    'name' => '公告',
                    'slug' => 'announcement',
                    'bg_color' => '#f59e0b', // 琥珀橙
                    'text_color' => '#ffffff',
                    'description' => '版块规约与重要更新公告',
                    'display_order' => 8,
                    'is_active' => true,
                ],
            ];

            $createdPrefixIds = [];
            foreach ($prefixes as $item) {
                $prefix = ThreadPrefix::updateOrCreate(['slug' => $item['slug']], $item);
                $createdPrefixIds[] = $prefix->id;
            }

            // ─────────────────────────────────────────────────────────────
            // 2. 🎯 核心关联：为数据库中已有的主题帖分配前缀标签
            // ─────────────────────────────────────────────────────────────
            $threads = Thread::all();

            if ($threads->isNotEmpty()) {
                foreach ($threads as $index => $thread) {
                    // 为置顶帖赋予 [Verified] 或 [公告] 标签
                    if ($thread->sticky) {
                        $thread->prefixes()->sync([$createdPrefixIds[0]]); // Verified
                        continue;
                    }

                    // 常规帖子随机绑定 1 ~ 2 个标签
                    $randomCount = rand(1, 2);
                    $randomPrefixIds = collect($createdPrefixIds)->random($randomCount)->all();
                    $thread->prefixes()->sync($randomPrefixIds);
                }

                $this->command->info("已为 {$threads->count()} 篇主题帖成功绑定彩色标签！");
            } else {
                $this->command->warn('当前数据库无 Thread 数据，仅初始化了标签字典。');
            }

            $this->command->info('✅ thread_prefixes 模拟数据填充完毕！');
        });
    }
}
