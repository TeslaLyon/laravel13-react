<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Forum;
use App\Models\Node;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class ForumSeeder extends Seeder
{
    /**
     * 运行 XenForo 四表架构的深度模拟数据填充
     */
    public function run(): void
    {

    // 🎯 1. 【核心安全保护】：仅清空论坛模块的 4 张表，绝不触碰 videos 表！
        Schema::disableForeignKeyConstraints(); // 临时关闭外键约束检查

        Post::truncate();   // 清空帖子回复表
        Thread::truncate(); // 清空主题表
        Forum::truncate();  // 清空版块表
        Node::truncate();   // 清空节点表

        Schema::enableForeignKeyConstraints();  // 重新启用外键约束检查

        // ----------------------------------------------------------------------
        // 🎯 1. 初始化测试用户池 (避免唯一性约束冲突)
        // ----------------------------------------------------------------------
        $users = [
            'admin' => $this->createUser('admin1@example.com', '系统管理员2', 'Admin2', 'password'),
            'alex'  => $this->createUser('alex@example.com', 'Alex_Dev', 'Alex', 'password'),
            'sarah' => $this->createUser('sarah@example.com', 'Sarah_UI', 'Sarah', 'password'),
            'bob'   => $this->createUser('bob@example.com', 'Bob_Backend', 'Bob', 'password'),
        ];

        // ----------------------------------------------------------------------
        // 🎯 2. 创建分类 1：官方社区 (Official Community)
        // ----------------------------------------------------------------------
        $officialCat = Node::firstOrCreate(['slug' => 'official'], [
            'parent_id'     => null,
            'node_type'     => 'category',
            'title'         => '官方社区',
            'description'   => '了解平台官方最新动态、政策规则与帮助文档',
            'display_order' => 1,
            'is_active'     => true,
        ]);

        // 2.1 版块：站务公告
        $newsNode = Node::firstOrCreate(['slug' => 'announcements'], [
            'parent_id'     => $officialCat->id,
            'node_type'     => 'forum',
            'title'         => '站务公告',
            'description'   => '平台重大功能升级、维护通知及规则变更公示',
            'icon'          => 'Megaphone',
            'display_order' => 1,
            'is_active'     => true,
        ]);
        $newsForum = Forum::firstOrCreate(['node_id' => $newsNode->id], ['allow_posting' => true]);

        // 2.2 外链节点：官方文档 (Link Forum)
        $docNode = Node::firstOrCreate(['slug' => 'official-docs'], [
            'parent_id'     => $officialCat->id,
            'node_type'     => 'link',
            'title'         => '官方开发开发者文档 (外部跳转)',
            'description'   => '查阅社区 REST API 接口与开发指南手册',
            'icon'          => 'ExternalLink',
            'display_order' => 2,
            'is_active'     => true,
        ]);
        Forum::firstOrCreate(['node_id' => $docNode->id], [
            'allow_posting' => false,
            'link_url'      => 'https://laravel.com/docs',
        ]);

        // ----------------------------------------------------------------------
        // 🎯 3. 创建分类 2：技术交流区 (Tech Discussion)
        // ----------------------------------------------------------------------
        $techCat = Node::firstOrCreate(['slug' => 'tech'], [
            'parent_id'     => null,
            'node_type'     => 'category',
            'title'         => '技术交流区',
            'description'   => '涵盖前端、后端、架构与人工智能等热门领域讨论',
            'display_order' => 2,
            'is_active'     => true,
        ]);

        // 3.1 版块：Web 全栈开发
        $webNode = Node::firstOrCreate(['slug' => 'web-fullstack'], [
            'parent_id'     => $techCat->id,
            'node_type'     => 'forum',
            'title'         => 'Web 全栈开发',
            'description'   => 'React, Vue, Laravel, Next.js 及现代 Web 架构讨论',
            'icon'          => 'Layers',
            'display_order' => 1,
            'is_active'     => true,
        ]);
        $webForum = Forum::firstOrCreate(['node_id' => $webNode->id], ['allow_posting' => true]);

        // 3.1.1 三级子版块：React 专区
        $reactSubNode = Node::firstOrCreate(['slug' => 'react-special'], [
            'parent_id'     => $webNode->id,
            'node_type'     => 'forum',
            'title'         => 'React & Next.js',
            'description'   => '探讨 React 19 Server Components 与 Next.js 最佳实践',
            'icon'          => 'Atom',
            'display_order' => 1,
            'is_active'     => true,
        ]);
        $reactSubForum = Forum::firstOrCreate(['node_id' => $reactSubNode->id], ['allow_posting' => true]);

        // 3.2 版块：AI 与深度学习
        $aiNode = Node::firstOrCreate(['slug' => 'ai-ml'], [
            'parent_id'     => $techCat->id,
            'node_type'     => 'forum',
            'title'         => 'AI 与深度学习',
            'description'   => 'LLM 本地部署、Prompt 工程与 AI 智能体应用探讨',
            'icon'          => 'Brain',
            'display_order' => 2,
            'is_active'     => true,
        ]);
        $aiForum = Forum::firstOrCreate(['node_id' => $aiNode->id], ['allow_posting' => true]);

        // ----------------------------------------------------------------------
        // 🎯 4. 创建分类 3：综合休闲区 (Casual Discussion)
        // ----------------------------------------------------------------------
        $casualCat = Node::firstOrCreate(['slug' => 'casual'], [
            'parent_id'     => null,
            'node_type'     => 'category',
            'title'         => '综合休闲区',
            'description'   => '工作之外的轻松日常、技术人茶水间与水吧',
            'display_order' => 3,
            'is_active'     => true,
        ]);

        $waterNode = Node::firstOrCreate(['slug' => 'watercooler'], [
            'parent_id'     => $casualCat->id,
            'node_type'     => 'forum',
            'title'         => '茶水间 / 闲聊',
            'description'   => '畅所欲言，分享生活趣事与装备硬件',
            'icon'          => 'Coffee',
            'display_order' => 1,
            'is_active'     => true,
        ]);
        $waterForum = Forum::firstOrCreate(['node_id' => $waterNode->id], ['allow_posting' => true]);

        // ----------------------------------------------------------------------
        // 🎯 5. 批量生成主题 (Threads) 与多楼层回复 (Posts)
        // ----------------------------------------------------------------------

        // 【主题 1】：站务公告版块 -> 平台 v2.5 版本上线
        $this->createThreadWithPosts(
            node: $newsNode,
            author: $users['admin'],
            title: '【官方公告】社区 v2.5 版本正式上线及性能优化说明',
            slug: 'community-v25-release',
            prefix: '公告',
            sticky: true,
            postsData: [
                ['user' => $users['admin'], 'text' => "亲爱的社区成员：\n\n我们非常高兴地宣布，社区 v2.5 版本已顺利部署完成！本次更新全面采用了 XenForo 官方四表架构（nodes + forums + threads + posts），结合 PostgreSQL 18 驱动，大厅渲染效率提升 60%。\n\n欢迎大家体验！", 'time' => now()->subDays(3)],
                ['user' => $users['alex'],  'text' => '热烈祝贺新版本上线！页面加载非常流畅，特别是右侧最新动态预览太方便了！', 'time' => now()->subDays(2)],
                ['user' => $users['sarah'], 'text' => 'UI 视觉风格非常细腻，赞一个！', 'time' => now()->subDays(1)],
            ]
        );

        // 【主题 2】：Web 全栈开发 -> Inertia::defer 优化
        $this->createThreadWithPosts(
            node: $webNode,
            author: $users['alex'],
            title: 'Inertia::defer 在大规模并发列表中的最佳优化实践方案',
            slug: 'inertia-defer-best-practices',
            prefix: '分享',
            sticky: false,
            postsData: [
                ['user' => $users['alex'],  'text' => "在处理复杂卡片列表时，将耗时的关联数据包裹在 Inertia::defer 中，可以让前端瞬间挂载 UI 骨架屏，极大地改善用户的感知流畅度...\n\n配合后端只提取必要属性，能避免 80% 的无用数据库查询开销。", 'time' => now()->subHours(10)],
                ['user' => $users['bob'],   'text' => '请问在 SSR 服务端渲染模式下，Inertia::defer 会阻塞首屏输出吗？', 'time' => now()->subHours(8)],
                ['user' => $users['alex'],  'text' => '@Bob_Backend 不会阻塞，defer 属性修饰的数据会在 HTTP 连接建立后通过独立通道异步加载。', 'time' => now()->subHours(5)],
            ]
        );

        // 【主题 3】：Web 全栈开发 -> Tailwind v4 体验
        $this->createThreadWithPosts(
            node: $webNode,
            author: $users['sarah'],
            title: 'Tailwind CSS v4.0 升级指南：全新的 Engine 与 CSS-first 配置体验',
            slug: 'tailwind-v4-upgrade-guide',
            prefix: '讨论',
            sticky: false,
            postsData: [
                ['user' => $users['sarah'], 'text' => 'Tailwind v4 放弃了传统的 `tailwind.config.js`，转而使用全新的 `@theme` CSS 指令！构建速度比之前快了近 10 倍！有人已经在生产环境试用了吗？', 'time' => now()->subHours(6)],
                ['user' => $users['admin'], 'text' => '我们社区的前端样式组件库后续也会计划迁移到 Tailwind v4！', 'time' => now()->subHours(2)],
            ]
        );

        // 【主题 4】：AI 与深度学习 -> Ollama 本地部署
        $this->createThreadWithPosts(
            node: $aiNode,
            author: $users['bob'],
            title: '如何使用 Ollama 在本地无痛部署 DeepSeek-R1 模型？',
            slug: 'ollama-deepseek-r1-deployment',
            prefix: '教程',
            sticky: true,
            postsData: [
                ['user' => $users['bob'],   'text' => "只需简单几步：\n1. 安装 Ollama 命令行工具\n2. 运行 `ollama run deepseek-r1:8b`\n3. 结合 Open-WebUI 即可拥有本地私有化 AI 助手！", 'time' => now()->subDays(1)],
                ['user' => $users['alex'],  'text' => '8B 版本的蒸馏模型在 16G 显存的显卡上运行非常丝滑，推理解答能力惊人！', 'time' => now()->subHours(12)],
            ]
        );

        // 【主题 5】：茶水间 -> 机械键盘选购指南
        $this->createThreadWithPosts(
            node: $waterNode,
            author: $users['sarah'],
            title: '程序员每日桌面搭配展示：你最喜欢用什么轴体的机械键盘？',
            slug: 'desk-setup-keyboard-discussion',
            prefix: '闲聊',
            sticky: false,
            postsData: [
                ['user' => $users['sarah'], 'text' => '最近换了静音红轴，办公室敲代码再也不怕打扰到同事了，大家平时写代码偏向用静音轴还是段落轴呢？', 'time' => now()->subHours(4)],
                ['user' => $users['bob'],   'text' => '坚定不移地选择青轴，噼里啪啦才有写代码的节奏感！', 'time' => now()->subHours(3)],
                ['user' => $users['alex'],  'text' => '静音黄轴 + 磁轴双拼，兼顾打字与偶尔的娱乐，绝配！', 'time' => now()->subHours(1)],
            ]
        );

        // ----------------------------------------------------------------------
        // 🎯 6. 重新刷新所有 Forums 版块的反范式统计与右侧最新发帖缓存
        // ----------------------------------------------------------------------
        $this->refreshAllForumsCache();
    }

    /**
     * 辅助方法：安全创建用户（防止 UNIQUE 规则冲突）
     */
    private function createUser(string $email, string $name, string $nickname, string $password): User
    {
        return User::where('email', $email)
            ->orWhere('nickname', $nickname)
            ->first() ?? User::create([
                'email'    => $email,
                'name'     => $name,
                'nickname' => $nickname,
                'password' => Hash::make($password),
            ]);
    }

    /**
     * 辅助方法：一键生成主题及其包含的楼层帖子
     */
    private function createThreadWithPosts(
        Node $node,
        User $author,
        string $title,
        string $slug,
        string $prefix,
        bool $sticky,
        array $postsData
    ): Thread {
        // 创建主题记录
        $thread = Thread::firstOrCreate(['slug' => $slug], [
            'node_id'          => $node->id,
            'user_id'          => $author->id,
            'username'         => $author->name,
            'title'            => $title,
            'prefix'           => $prefix,
            'sticky'           => $sticky,
            'discussion_open'  => true,
            'discussion_state' => 'visible',
            'view_count'       => rand(50, 800),
            'reply_count'      => count($postsData) - 1,
        ]);

        $firstPost = null;
        $lastPost  = null;

        // 循环创建楼层回复
        foreach ($postsData as $index => $pData) {
            $postTime = $pData['time'] ?? now();

            $post = Post::firstOrCreate([
                'thread_id' => $thread->id,
                'position'  => $index, // 0 为主帖 1楼，1,2,3 为跟帖
            ], [
                'user_id'        => $pData['user']->id,
                'username'       => $pData['user']->name,
                'message'        => $pData['text'],
                'is_first_post'  => $index === 0,
                'message_state'  => 'visible',
                'reaction_score' => rand(2, 30),
                'ip_address'     => '192.168.1.' . rand(10, 200),
                'created_at'     => $postTime,
                'updated_at'     => $postTime,
            ]);

            if ($index === 0) {
                $firstPost = $post;
            }
            $lastPost = $post;
        }

        // 更新主题的首尾楼层指针与最后活动时间
        if ($firstPost && $lastPost) {
            $thread->update([
                'first_post_id'      => $firstPost->id,
                'last_post_id'       => $lastPost->id,
                'last_post_user_id'  => $lastPost->user_id,
                'last_post_username' => $lastPost->username,
                'last_post_date'     => $lastPost->created_at,
            ]);
        }

        return $thread;
    }

    /**
     * 辅助方法：重新计算所有 Forum 的主题数、回复数及最新发帖缓存
     */
    private function refreshAllForumsCache(): void
    {
        $forums = Forum::all();

        foreach ($forums as $forum) {
            $nodeId = $forum->node_id;

            // 统计包含的主题数与总发言数
            $discussionCount = Thread::where('node_id', $nodeId)->count();
            $messageCount    = Post::whereIn('thread_id', Thread::where('node_id', $nodeId)->pluck('id'))->count();

            // 查出该版块最新的一条主题与回复
            $latestThread = Thread::where('node_id', $nodeId)->latest('last_post_date')->first();

            $forum->update([
                'discussion_count'   => $discussionCount,
                'message_count'      => $messageCount,
                'last_thread_id'     => $latestThread?->id,
                'last_thread_title'  => $latestThread?->title,
                'last_post_id'       => $latestThread?->last_post_id,
                'last_post_user_id'  => $latestThread?->last_post_user_id,
                'last_post_username' => $latestThread?->last_post_username,
                'last_post_date'     => $latestThread?->last_post_date,
            ]);
        }
    }
}
