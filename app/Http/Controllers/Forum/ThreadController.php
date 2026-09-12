<?php

declare(strict_types=1);

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Models\Forum;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ThreadController extends Controller
{
    /**
     * 展示主题帖详情 (第 1 页)
     */
    public function show(Request $request, Thread $thread, ?string $slug = null): Response|RedirectResponse
    {
        return $this->renderThreadPage($request, $thread, $slug, 1);
    }

    /**
     * 帖子独立分页入口 (第 2 页及以上: /page-{page})
     */
    public function page(Request $request, Thread $thread, ?string $slug = null, int $page = 1): Response|RedirectResponse
    {
        if ($page <= 1) {
            return redirect()->route('forum.threads.show', [
                'thread' => $thread->id,
                'slug' => $thread->slug,
            ], 301);
        }

        return $this->renderThreadPage($request, $thread, $slug, $page);
    }

    /**
     * 🎯 底层统一渲染与数据组装逻辑
     */
    private function renderThreadPage(Request $request, Thread $thread, ?string $slug, int $currentPage): Response|RedirectResponse
    {
        // 1. SEO 规范化 Slug 校验
        if ($slug !== null && $thread->slug && $slug !== $thread->slug) {
            $routeName = $currentPage > 1 ? 'forum.threads.show.page' : 'forum.threads.show';
            $params = ['thread' => $thread->id, 'slug' => $thread->slug];
            if ($currentPage > 1) {
                $params['page'] = $currentPage;
            }

            return redirect()->route($routeName, $params, 301);
        }

        // 2. 浏览量原子自增
        $thread->timestamps = false;
        $thread->increment('view_count');
        $thread->timestamps = true;

        // 3. 预加载主题自身关联
        $thread->loadMissing(['node', 'prefixes', 'user.primaryGroup', 'user.secondaryGroups']);

        // 4. 面包屑导航
        $node = $thread->node;
        $breadcrumbs = [
            ['title' => '首页', 'href' => '/'],
            ['title' => '论坛', 'href' => route('forum.index')],
            ['title' => $node ? $node->title : '讨论版块', 'href' => $node ? route('forum.nodes.show', $node->id) : null],
            ['title' => $thread->title, 'href' => null],
        ];

        // 🎯 5. 楼层分页查询 (Eager Loading 深度预加载用户组与勋章，彻底消灭 N+1)
        $perPage = 10;
        $posts = $thread->posts()
            ->visible()
            ->with([
                'editor',
                'user' => function ($query) {
                    $query->with([
                        // 预加载主用户组
                        'primaryGroup',
                        // 预加载需展示的次要组并按优先级排序
                        'secondaryGroups' => function ($q) {
                            $q->wherePivot('is_displayed', true)
                                ->orderByDesc('display_style_priority');
                        },
                    ]);
                }
            ])
            ->orderBy('position', 'asc')
            ->paginate(perPage: $perPage, page: $currentPage)
            ->through(function (Post $post) {
                /** @var User|null $author */
                $author = $post->user;

                // 🎯 收集并组装多重横幅 (Primary + Displayed Secondary)
                $banners = collect();
                $usernameCss = null;

                if ($author) {
                    $primary = $author->primaryGroup;
                    if ($primary) {
                        $usernameCss = $primary->username_css;
                        if (!empty($primary->banner_text)) {
                            $banners->push($primary);
                        }
                    }

                    // 合并需展示的次要用户组
                    if ($author->secondaryGroups) {
                        foreach ($author->secondaryGroups as $secGroup) {
                            if (!empty($secGroup->banner_text)) {
                                $banners->push($secGroup);
                            }
                        }
                    }
                }

                // 格式化横幅输出结构并按显示优先级排序
                $formattedBanners = $banners
                    ->sortByDesc('display_style_priority')
                    ->values()
                    ->map(fn(UserGroup $group) => [
                        'name' => $group->banner_text,
                        'bgColor' => $group->banner_bg_color ?: '#2563eb',
                        'textColor' => $group->banner_text_color ?: '#ffffff',
                        'icon' => $group->banner_icon,
                    ])
                    ->all();

                return [
                    'id' => $post->id,
                    'position' => $post->position,
                    'floorNumber' => $post->position + 1,
                    'isFirstPost' => (bool) $post->is_first_post,
                    'message' => $post->message,
                    'reactionScore' => $post->reaction_score,
                    'createdAt' => $post->created_at ? $post->created_at->diffForHumans() : '刚刚',
                    'editCount' => $post->edit_count,
                    'editedAt' => $post->edited_at ? $post->edited_at->diffForHumans() : null,
                    'editorName' => $post->editor?->name,

                    // 🎯 规范化作者数据画像
                    'author' => [
                        'id' => $author?->id ?? $post->user_id,
                        'name' => $author?->name ?? $post->username,
                        'username' => $author?->username ?? 'user_' . $post->user_id,
                        'avatar' => $author?->avatar_url,
                        'coverUrl' => null,
                        'usernameStyle' => $usernameCss,
                        'displayTitle' => $author?->custom_title ?: ($author?->cached_title ?: '初入江湖'),
                        'banners' => $formattedBanners,
                        'role' => $author?->primaryGroup?->title,
                        'joinedAt' => $author?->created_at ? $author->created_at->format('Y-m') : '近期',
                        'stats' => [
                            'postCount' => (int) ($author?->post_count ?? 0),
                            'reactionScore' => (int) ($author?->reaction_score ?? 0),
                            'totalCredits' => (float) ($author?->total_credits ?? 0.00),
                            'prestigePoints' => (int) ($author?->prestige_points ?? 0),
                            'enthusiasmPoints' => (int) ($author?->enthusiasm_points ?? 0),
                        ],
                        'badges' => [],
                    ],
                ];
            });

        // 6. 构造 SEO 元数据
        $baseUrl = route('forum.threads.show', ['thread' => $thread->id, 'slug' => $thread->slug]);
        $canonicalUrl = $currentPage === 1 ? $baseUrl : "{$baseUrl}/page-{$currentPage}";
        $lastPage = $posts->lastPage();

        $seo = [
            'canonicalUrl' => $canonicalUrl,
            'prevPageUrl' => $currentPage > 1
                ? ($currentPage === 2 ? $baseUrl : "{$baseUrl}/page-" . ($currentPage - 1))
                : null,
            'nextPageUrl' => $currentPage < $lastPage
                ? "{$baseUrl}/page-" . ($currentPage + 1)
                : null,
        ];

        return Inertia::render('forum/ThreadShow', [
            'breadcrumbs' => $breadcrumbs,
            'thread' => [
                'id' => $thread->id,
                'nodeId' => $thread->node_id,
                'title' => $thread->title,
                'slug' => $thread->slug,
                'isSticky' => (bool) $thread->sticky,
                'isLocked' => !(bool) $thread->discussion_open,
                'viewCount' => $thread->view_count,
                'replyCount' => $thread->reply_count,
                'createdAt' => $thread->created_at ? $thread->created_at->format('Y-m-d H:i') : '',
                'prefixes' => $thread->prefixes->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'bgColor' => $p->bg_color,
                    'textColor' => $p->text_color,
                ]),
                'author' => [
                    'id' => $thread->user_id,
                    'name' => $thread->user?->name ?? $thread->username,
                    'avatar' => $thread->user?->avatar_url,
                    'role' => $thread->user?->primaryGroup?->title ?? '社区会员',
                ],
            ],
            'posts' => $posts,
            'canReply' => (bool) $thread->discussion_open,
            'seo' => $seo,
        ]);
    }

    /**
     * 提交回帖：自动计算递增 position 并更新统计
     */
    public function storePost(Request $request, Thread $thread): RedirectResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:20000'],
        ]);

        if (!$thread->discussion_open) {
            return back()->with('error', '该主题已锁定，暂不支持回复。');
        }

        $user = $request->user();

        DB::transaction(function () use ($request, $thread, $user) {
            $maxPosition = (int) $thread->posts()->max('position');
            $newPosition = $maxPosition + 1;

            $post = $thread->posts()->create([
                'user_id' => $user->id,
                'username' => $user->name,
                'message' => $request->input('message'),
                'position' => $newPosition,
                'is_first_post' => false,
                'message_state' => 'visible',
                'reaction_score' => 0,
                'ip_address' => $request->ip(),
            ]);

            // 更新主题统计与最新回复人
            $thread->update([
                'reply_count' => $thread->reply_count + 1,
                'last_post_id' => $post->id,
                'last_post_user_id' => $user->id,
                'last_post_username' => $user->name,
                'last_post_date' => now(),
            ]);

            // 更新用户发帖量并重算总积分与阶梯头衔
            $user->increment('post_count');
            if (method_exists($user, 'refreshTotalCredits')) {
                $user->refreshTotalCredits();
            }

            // 更新版块统计
            Forum::where('id', $thread->node_id)->increment('post_count');
        });

        return back()->with('success', '回复发布成功！');
    }
}
