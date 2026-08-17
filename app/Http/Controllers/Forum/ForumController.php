<?php

declare(strict_types=1);

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Models\Node;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Thread;

class ForumController extends Controller
{
    public function index(Request $request): Response
    {
        // 查询所有顶层节点 (Category)，预加载其下属二级版块、forum 扩展属性及最新动态
        $categories = Node::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('display_order', 'asc')
            ->with([
                'children' => function ($query) {
                    $query->where('is_active', true)
                        ->orderBy('display_order', 'asc')
                        ->with(['forum', 'children:id,parent_id,title,slug,node_type']);
                }
            ])
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->title,
                    'nodes' => $category->children->map(function ($node) {
                        $forum = $node->forum;

                        return [
                            'id' => $node->id,
                            'name' => $node->title,
                            'slug' => $node->slug,
                            'nodeType' => $node->node_type, // 'forum', 'link', 'page'
                            'description' => $node->description ?: '',
                            'linkUrl' => $forum->link_url ?? null,
                            'threadCount' => $forum->discussion_count ?? 0,
                            'messageCount' => $forum->message_count ?? 0,

                            // 子节点
                            'subForums' => $node->children->map(fn($sub) => [
                                'id' => $sub->id,
                                'name' => $sub->title,
                                'slug' => $sub->slug,
                            ]),

                            // 右侧最新动态预览
                            'lastPost' => ($forum && $forum->last_thread_id) ? [
                                'threadId' => $forum->last_thread_id,
                                'threadTitle' => $forum->last_thread_title,
                                'authorName' => $forum->last_post_username ?? '匿名',
                                'createdAt' => $forum->last_post_date ? $forum->last_post_date->diffForHumans() : '',
                            ] : null,
                        ];
                    }),
                ];
            });

        return Inertia::render('forum/index', [
            'categories' => $categories,
        ]);
    }

    /**
     * 显示单个版块下的主题帖列表页
     */
    public function show($id): Response
    {
        $node = Node::with('forum')->findOrFail($id);

        // 分页查询该节点下的主题列表
        $threads = Thread::query()
            ->where('node_id', $node->id)
            ->where('discussion_state', 'visible')
            ->orderBy('sticky', 'desc') // 置顶帖排在最前面
            ->orderBy('last_post_date', 'desc') // 按最新回复时间倒序
            ->paginate(15)
            ->through(fn($thread) => [
                'id' => $thread->id,
                'nodeId' => $thread->node_id,
                'title' => $thread->title,
                'slug' => $thread->slug,
                'prefix' => $thread->prefix,
                'authorName' => $thread->username,
                'isSticky' => $thread->sticky,
                'isLocked' => !$thread->discussion_open,
                'viewCount' => $thread->view_count,
                'replyCount' => $thread->reply_count,
                'createdAt' => $thread->created_at->diffForHumans(),
                'lastPost' => $thread->last_post_id ? [
                    'authorName' => $thread->last_post_username ?? '匿名',
                    'createdAt' => $thread->last_post_date ? $thread->last_post_date->diffForHumans() : '',
                ] : null,
            ]);

        return Inertia::render('forum/show', [
            'node' => [
                'id' => $node->id,
                'name' => $node->title,
                'description' => $node->description,
            ],
            'threads' => $threads,
        ]);
    }
}
