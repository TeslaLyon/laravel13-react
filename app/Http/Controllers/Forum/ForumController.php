<?php

declare(strict_types=1);

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Models\Node;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Models\ForumNotice;

class ForumController extends Controller
{
    // username 字段是否保留
    public function index(Request $request): Response
    {
        $notices = ForumNotice::active()
            ->forIndex()
            ->get()
            ->map(fn(ForumNotice $notice) => [
                'id' => $notice->id,
                'title' => $notice->title,
                'content' => $notice->content,
                'type' => $notice->type, // info, warning, danger, success
                'linkUrl' => $notice->link_url,
                'linkText' => $notice->link_text ?? '查看详情',
                'isDismissible' => (bool) $notice->is_dismissible,
            ]);

        // 查询所有顶层节点 (Category)，预加载其下属二级版块、forum 扩展属性及最新动态
        $categories = Node::query()
            ->root()
            ->active()
            ->ordered()
            ->with([
                'activeChildren.forum',
                'activeChildren.activeChildren:id,parent_id,title,slug,node_type',
            ])
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'nodes' => $category->activeChildren->map(function ($node) {
                        $forum = $node->forum;

                        return [
                            'id' => $node->id,
                            'title' => $node->title,
                            'slug' => $node->slug,
                            'nodeType' => $node->node_type,
                            'description' => $node->description ?: '',

                            // 🎯 直接读取模型的 icon_url 属性
                            'iconUrl' => $node->icon_url,

                            'linkUrl' => $forum->link_url ?? null,
                            'threadCount' => $forum->discussion_count ?? 0,
                            'messageCount' => $forum->message_count ?? 0,

                            // 子版块
                            'subForums' => $node->activeChildren->map(fn($sub) => [
                                'id' => $sub->id,
                                'title' => $sub->title,
                                'slug' => $sub->slug,
                            ]),

                            // 右侧最新动态
                            'lastPost' => ($forum && $forum->last_thread_id) ? [
                                'threadId' => $forum->last_thread_id,
                                'threadTitle' => $forum->last_thread_title,
                                'slug' => $forum->last_thread_slug,
                                'createdAt' => $forum->last_post_date ? $forum->last_post_date->diffForHumans() : '刚刚',

                                // 🎯 彩色标签
                                'prefixes' => [
                                    [
                                        'id' => 1,
                                        'name' => 'Verified',
                                        'bgColor' => '#6f42c1',
                                        'textColor' => '#ffffff',
                                    ],
                                ],

                                // 🎯 仅输出基础作者信息，详细数据交由 Hover 时按需获取
                                'author' => [
                                    'id' => $forum->last_post_user_id,
                                    'name' => $forum->last_post_username ?? '匿名用户',
                                    'avatar' => $forum->lastPostUser?->avatar,
                                ],
                            ] : null,
                        ];
                    }),
                ];
            });

        // dd($categories);

        return Inertia::render('forum/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '论坛', 'href' => null],
            ],
            'notices' => $notices, // 🎯 下发公告列表
            'categories' => $categories,
        ]);
    }

    /**
     * 显示单个版块下的主题帖列表页
     */
    public function show(Node $node): Response
    {
        // 1. 🎯 获取当前版块有效公告列表（包含全局公告与本版专属公告）
        $notices = ForumNotice::active()
            ->forNode($node->id)
            ->get()
            ->map(fn(ForumNotice $notice) => [
                'id' => $notice->id,
                'title' => $notice->title,
                'content' => $notice->content,
                'type' => $notice->type, // info, warning, danger, success
                'linkUrl' => $notice->link_url,
                'linkText' => $notice->link_text ?? '查看详情',
                'isDismissible' => (bool) $notice->is_dismissible,
            ]);

        $threads = Thread::query()
            ->where('node_id', $node->id)
            ->with(['user', 'prefixes', 'lastPostUser'])
            // 🎯 核心修复：将 'is_sticky' 改为实际物理列名 'sticky'
            ->orderByDesc('sticky')         // 1. 置顶帖排在最前
            ->orderByDesc('created_at')     // 2. 正常帖子按发布时间倒序
            ->paginate(20)
            ->through(fn(Thread $thread) => [
                'id' => $thread->id,
                'nodeId' => $thread->node_id,
                'title' => $thread->title,
                'slug' => $thread->slug,

                // 🎯 字段映射：数据库物理列 sticky -> 前端 TypeScript 接口 isSticky
                'isSticky' => (bool) $thread->sticky,
                'isLocked' => !(bool) $thread->discussion_open, // discussion_open 映射为 isLocked

                'viewCount' => $thread->view_count,
                'replyCount' => $thread->reply_count,
                'createdAt' => $thread->created_at ? $thread->created_at->diffForHumans() : '刚刚',

                // 关联的前缀标签
                'prefixes' => $thread->prefixes->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'bgColor' => $p->bg_color,
                    'textColor' => $p->text_color,
                ]),

                // 发帖作者
                'author' => [
                    'id' => $thread->user_id,
                    'name' => $thread->user?->name ?? ($thread->username ?: '匿名'),
                    'avatar' => $thread->user?->avatar,
                ],
                'authorName' => $thread->user?->name ?? ($thread->username ?: '匿名'),
                'authorAvatar' => $thread->user?->avatar,

                // 最新回复动态
                'lastPost' => $thread->last_post_date ? [
                    'slug' => $thread->slug,
                    'createdAt' => $thread->last_post_date->diffForHumans(),
                    'authorName' => $thread->last_post_username ?? '匿名',
                    'author' => [
                        'id' => $thread->last_post_user_id,
                        'name' => $thread->last_post_username ?? '匿名',
                        'avatar' => $thread->lastPostUser?->avatar,
                    ],
                ] : null,
            ]);

        return Inertia::render('forum/show', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '论坛', 'href' => route('forum.index')],
                ['title' => $node->title, 'href' => null],
            ],
            'notices' => $notices, // 🎯 下发公告列表数据
            'node' => $node,
            'threads' => $threads,
        ]);
    }

    public function userHoverCard(Request $request, User $user): JsonResponse
    {
        // 🎯 1. 动态闭包加载：增加 badge_user 中间表的 is_equipped 与 expires_at 过滤
        $user->loadMissing([
            'badges' => function ($query) {
                $query->wherePivot('is_equipped', true) // 仅展示已佩戴的勋章
                    ->where(function ($q) {
                        // 有效期检查：未设置过期时间 (永久) 或 过期时间在当前时间之后
                        $q->whereNull('badge_user.expires_at')
                            ->orWhere('badge_user.expires_at', '>', now());
                    })
                    ->where('badges.is_active', true)    // 勋章处于激活状态
                    ->orderBy('badges.display_order', 'asc'); // 按权重升序排列
            }
        ]);

        // 🎯 2. 输出与前端 UserHoverCardData 契约完全对齐的 JSON 响应
        return response()->json([
            'id' => $user->id,
            'name' => $user->nickname ?: ($user->name ?: '社区创作者'),
            'username' => $user->username ?: 'creator_' . $user->id,
            'avatar' => $user->avatar ?: null,
            'coverUrl' => $user->cover_url ?: 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=800&auto=format&fit=crop&q=80',
            'role' => $user->role_name ?? '认证创作者',
            'joinedAt' => $user->created_at ? $user->created_at->format('Y-m') : '2024-03',
            'threadCount' => method_exists($user, 'threads') ? $user->threads()->count() : 128,
            'likeCount' => 1024,

            // 🎯 输出符合条件的勋章列表
            // 'badges' => $user->equippedBadges->map(fn($badge) => [
            //     'id' => $badge->id,
            //     'name' => $badge->name,
            //     'icon' => $badge->icon_url,
            //     'description' => $badge->description,
            // ]),
        ]);
    }
}
