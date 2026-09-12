<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PostSeeder extends Seeder
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

            if ($users->isEmpty()) {
                $users = collect([
                    User::factory()->create(['name' => '社区核心作者']),
                ]);
            }

            $replyMessages = [
                '非常感谢楼主的无私分享！内容干货满满，对新手创作者非常有启发。',
                '赞同楼主的观点，特别是关于订阅分成和粉丝互动那一段，深有同感。',
                '请问楼主使用的相机具体是什么型号？导出的预设色调太舒适了！',
                '蹲一个后续更新，建议版主直接加精置顶！',
                '前排支持！已经收藏并关注楼主了，期待下一期的深度专栏。',
                '这个技巧确实实用，我刚才在自己的项目里尝试了一下，效果立竿见影。',
            ];

            $totalPosts = 0;

            foreach ($threads as $thread) {
                // 如果该主题已有楼层则跳过
                if ($thread->posts()->exists()) {
                    continue;
                }

                $opUser = $thread->user ?? $users->random();
                $threadCreatedAt = $thread->created_at ?? now()->subDays(5);

                // 🎯 1. 创建 1 楼首帖正文：position = 0, is_first_post = true
                $firstPost = Post::create([
                    'thread_id' => $thread->id,
                    'user_id' => $opUser->id,
                    'username' => $opUser->name,
                    'message' => "这里是关于【{$thread->title}】的完整正文内容。\n\n感谢大家一直以来的关注与支持！我们在社区中持续探索更优质的内容创作与技术分享方案。欢迎各位在下方楼层踊跃留言讨论交流！",
                    'position' => 0,                     // 🎯 0 代表主楼 (1 楼)
                    'is_first_post' => true,             // 🎯 首帖标识
                    'message_state' => 'visible',
                    'reaction_score' => rand(10, 120),
                    'ip_address' => '127.0.0.1',
                    'created_at' => $threadCreatedAt,
                    'updated_at' => $threadCreatedAt,
                ]);

                $totalPosts++;

                // 🎯 2. 创建 2 ~ 5 条回帖：position 从 1 开始递增，is_first_post = false
                $replyCount = rand(2, 5);
                $lastReplyUser = $opUser;
                $lastReplyDate = $threadCreatedAt;

                for ($i = 1; $i <= $replyCount; $i++) {
                    $replyUser = $users->random();
                    $replyDate = $threadCreatedAt->copy()->addMinutes($i * rand(15, 60));

                    $replyPost = Post::create([
                        'thread_id' => $thread->id,
                        'user_id' => $replyUser->id,
                        'username' => $replyUser->name,
                        'message' => $replyMessages[array_rand($replyMessages)],
                        'position' => $i,                 // 🎯 1 = 2楼, 2 = 3楼...
                        'is_first_post' => false,
                        'message_state' => 'visible',
                        'reaction_score' => rand(0, 15),
                        'ip_address' => '127.0.0.1',
                        'created_at' => $replyDate,
                        'updated_at' => $replyDate,
                    ]);

                    $lastReplyUser = $replyUser;
                    $lastReplyDate = $replyDate;
                    $totalPosts++;
                }

                // 3. 更新主题统计
                $thread->update([
                    'first_post_id' => $firstPost->id,
                    'reply_count' => $replyCount,
                    'last_post_id' => $replyPost->id ?? $firstPost->id,
                    'last_post_user_id' => $lastReplyUser->id,
                    'last_post_username' => $lastReplyUser->name,
                    'last_post_date' => $lastReplyDate,
                ]);
            }

            $this->command->info("🎉 成功为所有主题帖填充了共 {$totalPosts} 篇楼层数据！");
        });
    }
}
