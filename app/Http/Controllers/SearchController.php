<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;
use App\Models\Actor;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Support\Sleep;

class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $request->input('q', '');

        // 初始化分组结果数组
        $results = [
            'help' => [],
            'blog' => [],
            'products' => [],
        ];

        if (!empty(trim($query))) {
            // 这里我们模拟从 Meilisearch 或数据库中查询不同模块的数据
            // 在实际项目中，你可以调用不同模型的 search() 方法

            // 1. 模拟搜索帮助中心
            $results['help'] = [
                ['id' => 'h1', 'title' => '如何修改密码？', 'excerpt' => '了解重置密码的详细步骤...', 'url' => '/help/article/h1'],
            ];

            // 2. 模拟搜索博客/动态
            $results['blog'] = [
                ['id' => 'b1', 'title' => 'Laravel 13 与 React 的完美结合', 'excerpt' => '探讨现代全栈开发的最佳实践...', 'url' => '/blog/b1'],
                ['id' => 'b2', 'title' => '2026年网站设计趋势', 'excerpt' => '极简主义与大圆角的回归...', 'url' => '/blog/b2'],
            ];

            // 3. 模拟搜索产品或服务
            $results['products'] = [
                ['id' => 'p1', 'title' => '高级订阅会员 (Pro)', 'excerpt' => '解锁所有功能，享受极速响应...', 'url' => '/pricing'],
            ];
        }

        return Inertia::render('search/index', [
            'query' => $query,
            'groupedResults' => $results,
        ]);
    }

    public function actors(Request $request): JsonResponse
    {
        $keyword = trim($request->query('q', ''));

        if (empty($keyword)) {
            return response()->json([]);
        }

        try {
            // 🚀 优先使用 Meilisearch 毫秒级检索
            $actors = Actor::search($keyword)->take(10)->get(['id', 'name', 'avatar']);
            if ($actors->isEmpty()) {
                $actors = Actor::query()
                    ->where('name', 'ILIKE', "{$keyword}%")
                    ->select(['id', 'name', 'avatar'])
                    ->limit(10)
                    ->get();
            }
        } catch (\Throwable) {
            $actors = Actor::query()
                ->where('name', 'ILIKE', "{$keyword}%")
                ->select(['id', 'name', 'avatar'])
                ->limit(10)
                ->get();
        }

        return response()->json($actors);
    }

    /**
     * 2. 搜索分类
     */
    public function categories(Request $request): JsonResponse
    {
        $keyword = trim($request->query('q', ''));

        if (empty($keyword)) {
            return response()->json([]);
        }

        $categories = Category::query()
            ->where('name', 'ILIKE', "{$keyword}%")
            ->select(['id', 'name', 'name_zh'])
            ->limit(10)
            ->get();
        // ->map(fn ($category) => [
        //     'id'   => $category->id,
        //     'name' => $category->name,
        // ]);
        Sleep::for(1000)->milliseconds();
        return response()->json($categories);
    }

    /**
     * 3. 搜索标签
     */
    public function tags(Request $request): JsonResponse
    {
        $keyword = trim($request->query('q', ''));

        if (empty($keyword)) {
            return response()->json([]);
        }

        $tags = Tag::query()
            ->where('name', 'ILIKE', "{$keyword}%")
            ->select(['id', 'name'])
            ->limit(10)
            ->get();
        // ->map(fn ($tag) => [
        //     'id'   => $tag->id,
        //     'name' => $tag->name,
        // ]);
        Sleep::for(1000)->milliseconds();
        return response()->json($tags);
    }
}
