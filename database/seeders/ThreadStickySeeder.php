<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Node;
use App\Models\Thread;
use App\Models\ThreadPrefix;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ThreadStickySeeder extends Seeder
{
    /**
     * 运行置顶主题模拟数据填充
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. 获取所有普通论坛版块
            $forumNodes = Node::where('node_type', 'forum')->get();

            if ($forumNodes->isEmpty()) {
                $this->command->warn('未找到任何版块节点，请先运行 ForumNodeSeeder。');
                return;
            }

            // 2. 获取发帖管理用户与标签池
            $adminUser = User::first() ?? User::factory()->create([
                'name' => '社区官方管理组',
                'email' => 'admin@forum.com',
            ]);
            $users = User::all();

            $verifiedPrefix = ThreadPrefix::where('slug', 'verified')->first();
            $announcementPrefix = ThreadPrefix::where('slug', 'announcement')->first();
            $requestPrefix = ThreadPrefix::where('slug', 'request')->first();
            $onlyFansPrefix = ThreadPrefix::where('slug', 'onlyfans')->first();
            $cosplayPrefix = ThreadPrefix::where('slug', 'cosplay')->first();

            // 3. 为不同版块预设专属的高质量置顶主题语料库
            $stickyTopicTemplates = [
                // 创作者交流专区
                'creators-hub' => [
                    [
                        'title' => '【官方指引】创作者认证通道、分成结算与权益保障细则 (2026 最新版)',
                        'open' => false, // 锁定回复
                        'prefixes' => array_filter([$announcementPrefix?->id, $verifiedPrefix?->id]),
                        'views' => 45200,
                        'replies' => 0,
                    ],
                    [
                        'title' => '【精华导航】全网热门拍摄器材、布光技巧与 4K 调色工程预设汇总帖',
                        'open' => true,
                        'prefixes' => array_filter([$verifiedPrefix?->id, $onlyFansPrefix?->id]),
                        'views' => 28900,
                        'replies' => 156,
                    ],
                    [
                        'title' => '【月度征集】2026 夏季优质原创作品评选与流量扶持报名入口',
                        'open' => true,
                        'prefixes' => array_filter([$announcementPrefix?->id]),
                        'views' => 18300,
                        'replies' => 92,
                    ],
                ],

                // 综合娱乐交流
                'general-discussion' => [
                    [
                        'title' => '【全站必读】社区公约、行为规范及违规内容阶梯处罚条例',
                        'open' => false,
                        'prefixes' => array_filter([$announcementPrefix?->id]),
                        'views' => 68000,
                        'replies' => 0,
                    ],
                    [
                        'title' => '【官方答疑】新手常见问题 FAQ 汇总与账号勋章获取指南',
                        'open' => true,
                        'prefixes' => array_filter([$verifiedPrefix?->id, $requestPrefix?->id]),
                        'views' => 31200,
                        'replies' => 245,
                    ],
                    [
                        'title' => '【摄影专栏】2026 年度全国大型漫展排期、参展嘉宾及同好面基总帖',
                        'open' => true,
                        'prefixes' => array_filter([$cosplayPrefix?->id]),
                        'views' => 19500,
                        'replies' => 88,
                    ],
                ],

                // 官方公告与规约
                'official-announcements' => [
                    [
                        'title' => '【重大更新】论坛全新勋章墙系统与悬浮名片功能正式上线公告',
                        'open' => true,
                        'prefixes' => array_filter([$announcementPrefix?->id, $verifiedPrefix?->id]),
                        'views' => 52000,
                        'replies' => 310,
                    ],
                    [
                        'title' => '【安全提醒】警惕冒充官方人员进行私下交易的防骗预警',
                        'open' => false,
                        'prefixes' => array_filter([$announcementPrefix?->id]),
                        'views' => 41000,
                        'replies' => 0,
                    ],
                ],

                // 默认/其他子版块通用置顶
                'default' => [
                    [
                        'title' => '【版规置顶】本版发帖格式要求、资源求助规范与免责声明',
                        'open' => false,
                        'prefixes' => array_filter([$announcementPrefix?->id]),
                        'views' => 12500,
                        'replies' => 0,
                    ],
                    [
                        'title' => '【精华置顶】本版优秀历史技术分享帖与常用工具包归档索引',
                        'open' => true,
                        'prefixes' => array_filter([$verifiedPrefix?->id]),
                        'views' => 9800,
                        'replies' => 42,
                    ],
                ],
            ];

            $totalStickyCount = 0;

            // 4. 遍历版块生成置顶帖
            foreach ($forumNodes as $node) {
                $templates = $stickyTopicTemplates[$node->slug] ?? $stickyTopicTemplates['default'];

                foreach ($templates as $index => $item) {
                    $postAuthor = ($item['open'] === false) ? $adminUser : $users->random();
                    $createdAt = now()->subDays(rand(10, 60))->subHours($index * 3);

                    // 🎯 核心标记：sticky = true
                    $thread = Thread::create([
                        'node_id' => $node->id,
                        'user_id' => $postAuthor->id,
                        'username' => $postAuthor->name,
                        'title' => $item['title'],
                        'slug' => Str::slug($item['title']) . '-' . Str::random(5),
                        'sticky' => true,                       // 🎯 置顶标志
                        'discussion_open' => (bool) $item['open'], // 是否开放回复
                        'discussion_state' => 'visible',
                        'view_count' => $item['views'],
                        'reply_count' => $item['replies'],
                        'last_post_user_id' => $item['replies'] > 0 ? $users->random()->id : $postAuthor->id,
                        'last_post_username' => $item['replies'] > 0 ? $users->random()->name : $postAuthor->name,
                        'last_post_date' => $item['replies'] > 0 ? now()->subMinutes(rand(10, 300)) : $createdAt,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    // 绑定彩色前缀
                    if (!empty($item['prefixes'])) {
                        $thread->prefixes()->sync($item['prefixes']);
                    }

                    $totalStickyCount++;
                }
            }

            $this->command->info("🎉 成功为 {$forumNodes->count()} 个版块创建了 {$totalStickyCount} 篇高质量置顶主题！");
        });
    }
}
