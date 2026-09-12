<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Post1Seeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $threads = Thread::all();
            $users = User::all();

            if ($threads->isEmpty()) {
                $this->command->warn('未找到任何主题帖，请先运行 ThreadSeeder。');
                return;
            }

            // 如果用户不足，生成一批基础测试用户
            if ($users->count() < 5) {
                $users = collect([
                    User::factory()->create(['name' => '架构先行者', 'username' => 'architect_dev']),
                    User::factory()->create(['name' => 'UI设计美学', 'username' => 'design_master']),
                    User::factory()->create(['name' => '全栈极客', 'username' => 'fullstack_pro']),
                    User::factory()->create(['name' => '开源发烧友', 'username' => 'opensource_fan']),
                    User::factory()->create(['name' => '社区核心作者', 'username' => 'core_creator']),
                ]);
            }

            // 🎯 清理旧楼层数据，确保重新填充时 position 序号严格从 0 开始
            Post::query()->forceDelete();

            // 🎯 多元拟真语料库
            $mockReplies = [
                "非常认同楼主在主楼中提到的观点！特别是针对性能优化这一块，我们在实际生产环境中测试过，效果非常显著。\n建议大家在应用时也注意配合 Redis 缓存一起使用。",
                "感谢楼主的干货分享！请问一下在处理高并发写入时，这种方案是否会出现数据库死锁问题？有没有相关的压测指标可以参考呢？",
                "mark 一下，前排支持优质技术贴！已经把这篇文章分享给团队里的小伙伴了，期待楼主下一期的深度专栏更新！🎉",
                "补充一个可能遇到的小坑：如果是部署在 Docker 容器环境内，记得检查一下文件权限与时区配置，否则可能会导致定时任务未能按预期触发。",
                "排版赏心悦目，内容干货满满！给楼主点赞表态了！👍 社区就需要多一些这样高质量的原创深度解析。",
                "针对第 3 点提到的方案，我个人更推荐采用异步队列处理机制。这样可以最大化减轻主数据库的瞬时 I/O 压力，系统的伸缩性也会更好。",
                "学习了！之前一直对底层原理半知半解，看了楼主绘制的架构图瞬间豁然开朗。期待加精置顶！",
                "收藏备用。正好手头的新项目在做技术选型，楼主给出的对比表格非常具有指导意义，省去了很多试错成本。",
                "亲测有效！刚刚按照主楼提供的步骤在本地开发环境跑通了，配置过程非常丝滑，赞！",
                "楼主能不能出一个配套的视频演示或者 GitHub Demo 仓库？感觉结合源码调试起来会更加直观易懂。",
                "这个思路确实独辟蹊径！我们之前尝试过传统方案，维护成本极高，楼主这种解耦设计确实优雅不少。",
            ];

            $totalPosts = 0;

            foreach ($threads as $thread) {
                $opUser = $thread->user ?? $users->random();
                $threadCreatedAt = $thread->created_at ?? now()->subDays(rand(3, 10));

                // 🎯 1. 创建 1 楼首帖正文 (position = 0, is_first_post = true)
                $firstPost = Post::create([
                    'thread_id' => $thread->id,
                    'user_id' => $opUser->id,
                    'username' => $opUser->name,
                    'message' => "欢迎阅读关于【{$thread->title}】的完整主题内容。\n\n在现代数字化创作与社区生态中，构建清晰、严谨且高内聚的技术体系是每个创作者的必经之路。本文将从架构设计、性能调优与最佳实践三个维度展开深入探讨。\n\n欢迎各位在下方楼层留下你的观点与疑问，我们共同交流探讨！",
                    'position' => 0,                     // 🎯 0 代表 1 楼楼主正文
                    'is_first_post' => true,
                    'message_state' => 'visible',
                    'reaction_score' => rand(15, 150),
                    'ip_address' => '127.0.0.1',
                    'created_at' => $threadCreatedAt,
                    'updated_at' => $threadCreatedAt,
                ]);

                $totalPosts++;

                // 🎯 2. 为该主题随机生成 8 ~ 14 篇跟帖回复 (在每页 3 条下可生成 3 ~ 5 页数据)
                $replyCount = rand(8, 14);
                $lastReplyUser = $opUser;
                $lastReplyDate = $threadCreatedAt;
                $lastPostId = $firstPost->id;

                for ($i = 1; $i <= $replyCount; $i++) {
                    $replyUser = $users->random();
                    $replyDate = $threadCreatedAt->copy()->addMinutes($i * rand(20, 90));
                    $isEdited = rand(1, 10) <= 2; // 20% 概率模拟被编辑过的帖子

                    $replyPost = Post::create([
                        'thread_id' => $thread->id,
                        'user_id' => $replyUser->id,
                        'username' => $replyUser->name,
                        'message' => $mockReplies[array_rand($mockReplies)],
                        'position' => $i,                 // 🎯 position 严格从 1 开始顺序递增
                        'is_first_post' => false,
                        'message_state' => 'visible',
                        'reaction_score' => rand(0, 30),
                        'ip_address' => '127.0.0.1',
                        'edit_count' => $isEdited ? rand(1, 3) : 0,
                        'edited_at' => $isEdited ? $replyDate->copy()->addMinutes(10) : null,
                        'edited_by_user_id' => $isEdited ? $replyUser->id : null,
                        'created_at' => $replyDate,
                        'updated_at' => $replyDate,
                    ]);

                    $lastReplyUser = $replyUser;
                    $lastReplyDate = $replyDate;
                    $lastPostId = $replyPost->id;
                    $totalPosts++;
                }

                // 🎯 3. 严格同步更新主题表的聚合计数与最新回复人信息
                $thread->update([
                    'first_post_id' => $firstPost->id,
                    'reply_count' => $replyCount,
                    'last_post_id' => $lastPostId,
                    'last_post_user_id' => $lastReplyUser->id,
                    'last_post_username' => $lastReplyUser->name,
                    'last_post_date' => $lastReplyDate,
                ]);
            }

            $this->command->info("🎉 成功为所有主题帖填充了共 {$totalPosts} 篇楼层数据（每个主题均拥有 3~5 页的分页数据）！");
        });
    }
}
