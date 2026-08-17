<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Actor;
use Illuminate\Http\Request;
use Illuminate\Support\Sleep;

// TODO:考虑演员别名存储问题
class ActorController extends Controller
{
    public function index(Request $request)
    {
        // 1. 获取快捷筛选参数（默认 'all'）
        $filter = $request->input('filter', 'all');
        $perPage = 1; // 设置固定的每页条数

        // 2. 构建主演员列表查询
        $query = Actor::query();

        // 3. 应用筛选和排序条件（默认按照 created_at 降序）
        match ($filter) {
            'most_subscribed' => $query->orderByDesc('subscribers_count'),
            'top_rated' => $query->orderByDesc('rating'),
            'large_bust' => $query->where('bust_size', '>=', 90)->orderByDesc('created_at'),
            'big_booty' => $query->where('booty_size', '>=', 90)->orderByDesc('created_at'),
            'newbie' => $query->orderByDesc('created_at'),
            'most_works' => $query->orderByDesc('videos_count'),
            default => $query->orderByDesc('created_at'),
        };

        // 🎯 4. 在 render 之前直接执行分页！
        // 这样 paginate() 会在主请求中自动解析 URL 里的 ?page=2 等参数，并附带 withQueryString()
        $actors = $query->paginate($perPage)->withQueryString();

        // 5. 返回 Inertia 渲染（风格完全对齐 VideoController）
        return Inertia::render('actor/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '演员', 'href' => null],
            ],
            // 传递当前筛选条件给前端
            'filters' => $request->only(['filter']),
            // 传递 perPage 给前端，供骨架屏在延迟数据到达前使用
            'perPage' => $perPage,

            // 🎯 6. 使用 Inertia::defer 延迟返回已分页好的 $actors 数据
            'actors' => Inertia::defer(function () use ($actors) {
                // 如果需要观察前端骨架屏效果，可以保留这行模拟延迟，生产环境删除即可
                Sleep::for(2000)->milliseconds();

                return $actors;
            })
        ]);
    }

    public function show(Request $request, Actor $actor, string $slug, string $tab = 'home')
    {
        // TODO:演员在多个别名时，该如何访问到主页呢？
        // TODO:在所有详情页 show 方法中增加对 slug 的对比检测

        // 1. 延迟预加载详情表数据
        // 因为 $actor 已经通过路由模型绑定查询出来了，我们使用 load() 追加查询 detail 关联
        abort_if($actor->slug !== $slug, 404);

        // 2. 延迟预加载关联详情表数据
        $actor->load('detail');

        // 3. 处理关注状态逻辑
        $user = $request->user();
        $initisFollowed = false;
        if ($user) {
            $initisFollowed = $actor->viaLoveReactant()->isReactedBy($user, 'FollowActor');
        }

        // 4. 数据结构拍平处理，适配前端组件
        $actorData = $actor->toArray();
        if ($actor->detail) {
            $actorData['basic_info'] = $actor->detail->basic_info ?? [];
            $actorData['physical_info'] = $actor->detail->physical_info ?? [];
            $actorData['socials'] = $actor->detail->socials ?? [];
            unset($actorData['detail']);
        } else {
            $actorData['basic_info'] = [];
            $actorData['physical_info'] = [];
            $actorData['socials'] = [];
        }

        // 5. 返回响应：使用 Inertia::defer 延迟加载大体积/慢查询数据
        return Inertia::render('actor/show', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '演员', 'href' => route('actors.index')],
                ['title' => $actor->name, 'href' => null],
            ],
            'actor' => $actorData,
            'initisFollowed' => $initisFollowed,
            'currentTab' => $tab,

            // 🌟 首页需要显示的最新少量数据（使用 defer 延迟拉取）
            'latestVideos' => Inertia::defer(
                fn() => $actor->videos()
                    ->with('channel:id,name,slug,avatar,data_crawl_type')
                    ->latest()
                    ->take(3)
                    ->select([
                        'id',
                        'name',
                        'slug',
                        'channel_id',
                        'list_img',
                        'preview',
                        'release_at',
                        'is_4k',
                        'is_vr',
                        'likes_count',
                        'favorites_count',
                        'created_at',
                        'country'
                    ])
                    ->get()
            ),
            // 'latestPhotos' => Inertia::defer(fn() => $actor->images()->latest()->take(4)->get()),
            'latestPhotos' => [],

            // 🌟 视频 Tab & 图片 Tab 对应的分页数据（使用 defer 延迟拉取）
            'paginatedVideos' => Inertia::defer(
                fn() => $actor->videos()
                    ->with('channel:id,name,slug,avatar,data_crawl_type')
                    ->latest()
                    ->select([
                        'id',
                        'name',
                        'slug',
                        'channel_id',
                        'list_img',
                        'preview',
                        'release_at',
                        'is_4k',
                        'is_vr',
                        'likes_count',
                        'favorites_count',
                        'created_at',
                        'country'
                    ])
                    ->paginate(12)
                    ->withQueryString()
            ),
            // 'paginatedPhotos' => Inertia::defer(fn() => $actor->images()->latest()->paginate(15)->withQueryString()),
            'paginatedPhotos' => [],
        ]);
    }

    public function follow(Request $request, Actor $actor)
    {
        $user = $request->user();
        $reacter = $user->viaLoveReacter();

        $isFollow = false;
        $msg = '操作成功';

        if ($reacter->hasReactedTo($actor, "FollowActor")) {
            $reacter->unreactTo($actor, "FollowActor");
            $isFollow = false;
            $msg = '已取消关注';
        } else {
            $reacter->reactTo($actor, "FollowActor");
            $isFollow = true;
            $msg = '关注成功';
        }

        Sleep::for(2000)->milliseconds();
        return response()->json([
            'status' => $isFollow,
            'message' => $msg,
        ]);
    }

    public function menuStatus(Request $request, Actor $actor)
    {
        $user = $request->user();
        $isFollow = false;
        if ($user) {
            $isFollow = $actor->viaLoveReactant()->isReactedBy($user, "FollowActor");
        }
        Sleep::for(2000)->milliseconds();
        return response()->json([
            'status' => $isFollow,
        ]);
    }
}
