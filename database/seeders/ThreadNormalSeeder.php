<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Forum;
use App\Models\Node;
use App\Models\Thread;
use App\Models\ThreadPrefix;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ThreadNormalSeeder extends Seeder
{
    /**
     * 运行普通主题帖填充
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. 获取所有普通版块节点（排除纯分类大块 category 与外链 link）
            $forumNodes = Node::where('node_type', 'forum')->get();

            if ($forumNodes->isEmpty()) {
                $this->command->warn('未找到任何 node_type 为 forum 的版块节点，请先运行 ForumNodeSeeder。');
                return;
            }

            // 2. 获取用户池与前缀标签池
            $users = User::all();
            if ($users->isEmpty()) {
                $users = collect([
                    User::factory()->create([
                        'name' => 'Angie Griffin',
                        'email' => 'creator@example.com',
                        'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&auto=format&fit=crop&q=80',
                    ]),
                ]);
            }

            $prefixes = ThreadPrefix::where('is_active', true)->get();

            // 3. 预设丰富且贴合真实社区的主题标题语料库
            $topicPool = [
                'creators-hub' => [
                    '关于 2026 年创作者收益分成与扶持计划解读',
                    'Girlsgotrhythm - 最新一期作品合辑已更新',
                    '新手创作者如何打造个人 IP 与提高粉丝粘性？',
                    '摄影棚灯光布置与 4K 高码率视频导出参数分享',
                    '探讨一下订阅制平台与一次性买断制的优缺点',
                    '【经验】如何与粉丝进行高效且有边界感的日常互动？',
                    '个人作品版权保护与维权心得总结',
                ],
                'general-discussion' => [
                    'Centolain - 本周热门二次元番剧吐槽交流',
                    '大家平时都用什么配置的电脑进行视频剪辑与渲染？',
                    '聊聊近期有哪些值得入手的独立神作游戏',
                    '周末摄影外拍活动招募令（附集合地点与路线）',
                    '2026 年度最受期待的展会行程与 Coser 名单汇总',
                    '深夜闲聊：你入坑当前爱好的契机是什么？',
                ],
                'official-announcements' => [
                    '【公告】社区违规内容处理细则与举报机制公示',
                    '【更新】个人中心勋章系统与悬浮资料卡正式上线！',
                    '【维护】8 月底服务器架构升级与数据库优化预告',
                ],
                'default' => [
                    '请教各位大佬一个关于资源下载与格式转换的问题',
                    '新人报道！分享一套自己整理的高清壁纸合集',
                    '大家对于版块近期的交流氛围有什么改进建议吗？',
                    '推荐几个非常好用的后期调色预设（附网盘链接）',
                    '打卡第 30 天，记录一下自己的成长历程',
                ],
            ];

            $totalCreatedThreads = 0;

            // 4. 遍历每个版块节点并生成普通帖子
            foreach ($forumNodes as $node) {
                // 选取匹配版块 slug 的语料，若无匹配则使用默认语料
                $titles = $topicPool[$node->slug] ?? $topicPool['default'];

                $nodeCreatedThreads = [];

                foreach ($titles as $index => $title) {
                    $author = $users->random();
                    // 🎯 时间阶梯：第一篇为 10 分钟前，随后逐渐递减到几天前，形成完美的倒序列表
                    $createdAt = now()->subMinutes(($index + 1) * 45)->subHours($index * 2);

                    $viewCount = rand(80, 3500);
                    $replyCount = rand(0, 48);

                    // 创建普通帖子 (sticky = false)
                    $thread = Thread::create([
                        'node_id' => $node->id,
                        'user_id' => $author->id,
                        'username' => $author->name,
                        'title' => $title,
                        'slug' => Str::slug($title) . '-' . Str::random(5),
                        'sticky' => false,                  // 🎯 普通帖子
                        'discussion_open' => true,          // 开放回复
                        'discussion_state' => 'visible',
                        'view_count' => $viewCount,
                        'reply_count' => $replyCount,
                        'last_post_user_id' => $replyCount > 0 ? $users->random()->id : $author->id,
                        'last_post_username' => $replyCount > 0 ? $users->random()->name : $author->name,
                        'last_post_date' => $replyCount > 0 ? $createdAt->copy()->addMinutes(rand(5, 60)) : $createdAt,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    // 🎯 随机绑定 1 ~ 2 个彩色前缀标签
                    if ($prefixes->isNotEmpty()) {
                        $randomPrefixCount = rand(1, min(2, $prefixes->count()));
                        $assignedPrefixIds = $prefixes->random($randomPrefixCount)->pluck('id')->all();
                        $thread->prefixes()->sync($assignedPrefixIds);
                    }

                    $nodeCreatedThreads[] = $thread;
                    $totalCreatedThreads++;
                }

                // 5. 🎯 核心同步：更新该版块在 forums 表中的计数与最新动态反范式缓存
                if (!empty($nodeCreatedThreads)) {
                    $latestThread = $nodeCreatedThreads[0]; // 最新的帖子
                    $latestThread->loadMissing('prefixes');

                    // 序列化前缀为 JSON 缓存
                    $prefixCache = $latestThread->prefixes->map(fn ($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'bgColor' => $p->bg_color,
                        'textColor' => $p->text_color,
                    ])->toArray();

                    $totalMessages = collect($nodeCreatedThreads)->sum(fn ($t) => $t->reply_count + 1);

                    Forum::updateOrCreate(
                        ['node_id' => $node->id],
                        [
                            'discussion_count' => count($nodeCreatedThreads),
                            'message_count' => $totalMessages,
                            'last_thread_id' => $latestThread->id,
                            'last_thread_title' => $latestThread->title,
                            'last_thread_prefixes' => $prefixCache, // 🎯 彩色标签 JSON 缓存
                            'last_post_user_id' => $latestThread->last_post_user_id,
                            'last_post_username' => $latestThread->last_post_username,
                            'last_post_date' => $latestThread->last_post_date,
                        ]
                    );
                }
            }

            $this->command->info("🎉 成功为 {$forumNodes->count()} 个版块生成了共 {$totalCreatedThreads} 篇普通主题帖，并同步更新了缓存！");
        });
    }
}
