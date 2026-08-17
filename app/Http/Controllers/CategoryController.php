<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Sleep;
use Illuminate\Support\Facades\Auth;


class CategoryController extends Controller
{
    /**
     * 显示分类/频道列表页 (支持分页与 Inertia 延迟加载)
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        return Inertia::render('category/index', [
            // 首屏即时返回面包屑导航
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '探索频道', 'href' => null],
            ],

            // 使用 Inertia::defer 延迟加载包含分页的数据
            'channels' => Inertia::defer(function () use ($request) {
                // 每页数量限制 (默认 12 条，可由前端控制，最大不超过 48 条)
                $perPage = min((int) $request->input('per_page', 12), 48);
                Sleep::for(500)->milliseconds();
                return Category::query()
                    ->orderBy('sort', 'asc') // 按照 sort 字段升序排列
                    ->paginate($perPage)
                    ->withQueryString() // 保留 URL 中的筛选与分页参数
                    ->through(fn($category) => [
                        'id' => $category->id,
                        // 优先显示中文名 name_zh，没有则显示 name
                        'name' => $category->name_zh ?: $category->name,
                        'slug' => $category->slug,
                        'description' => $category->description ?: '探索该频道下的优质内容与创作者。',
                        // 图片依次按照 avatar_horizontal -> avatar 降级
                        'coverImage' => $category->avatar_horizontal
                            ?: ($category->avatar
                                ?: 'https://images.pexels.com/photos/1181671/pexels-photo-1181671.jpeg?auto=compress&cs=tinysrgb&w=800'),
                        // 直接使用表中的 video_num 统计字段
                        'itemCount' => (string) $category->video_num,
                        'followCount' => $category->follow_num,
                    ]);
            }),
        ]);
    }

    /**
     * 分类详情页渲染方法
     *
     * @param Request $request
     * @param Category $category 隐式路由绑定
     * @param string $tab 当前 Tab 选项 (home | videos | photos)
     * @return Response
     */
    public function show(Request $request, Category $category, string $slug, string $tab = 'home'): Response
    {
        // 1. 获取当前登录用户是否已关注该分类 (根据项目实际关注逻辑调整)
        $isFollowed = Auth::check()
            ? Auth::user()->followedCategories()->where('category_id', $category->id)->exists()
            : false;

        // 2. 处理搜索关键词 (支持在分类详情内部搜索)
        $searchKeyword = $request->input('search');

        return Inertia::render('category/show', [
            // 首屏同步返回的基础实体数据 (适配 BaseDetailShow 的 EntityData 结构)
            'entity' => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name_zh ? $category->name_zh . ' | ' . $category->name : $category->name,
                'avatar' => $category->avatar,
                // 'banner' => $category->avatar_horizontal,
                'follow_num' => $category->follow_num,
                'bio' => $category->description ?: '探索该分类下的优质内容与创作者。',
                'nicknames' => ['热门', '推荐'], // 标签角标
            ],

            'currentTab' => $tab,
            'initisFollowed' => $isFollowed,

            // 🎯 3. 使用 Inertia::defer 延迟加载最新视频 (首页 Tab 场景)
            'latestVideos' => Inertia::defer(function () use ($category) {
                return $category->videos()
                    ->when(method_exists($category->videos(), 'scopePublished'), fn($q) => $q->published())
                    ->latest()
                    ->take(6)
                    ->get();
            }),

            // 🎯 4. 使用 Inertia::defer 延迟加载最新图片 (首页 Tab 场景)
            // 'latestPhotos' => Inertia::defer(function () use ($category) {
            //     return $category->photos()
            //         ->latest()
            //         ->take(5)
            //         ->get();
            // }),
            'latestPhotos' => [],

            // 🎯 5. 使用 Inertia::defer 延迟加载视频分页列表 (视频 Tab 场景)
            'paginatedVideos' => Inertia::defer(function () use ($category, $searchKeyword) {
                return $category->videos()
                    ->when(method_exists($category->videos(), 'scopePublished'), fn($q) => $q->published())
                    ->when($searchKeyword, fn($q) => $q->where('title', 'like', "%{$searchKeyword}%"))
                    ->latest()
                    ->paginate(12)
                    ->withQueryString();
            }),

            // 🎯 6. 使用 Inertia::defer 延迟加载图片分页列表 (图片 Tab 场景)
            // 'paginatedPhotos' => Inertia::defer(function () use ($category, $searchKeyword) {
            //     return $category->photos()
            //         ->when($searchKeyword, fn($q) => $q->where('title', 'like', "%{$searchKeyword}%"))
            //         ->latest()
            //         ->paginate(15)
            //         ->withQueryString();
            // }),
            'paginatedPhotos' => [],
        ]);
    }
}
