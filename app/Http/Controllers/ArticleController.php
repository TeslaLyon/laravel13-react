<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('article/index', [
            // 1. 面包屑导航数据 (即时返回，供首屏直接渲染)
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '推荐文章', 'href' => null],
            ],

            // 2. 使用 Inertia::defer 异步延迟加载文章数据，提高首屏渲染速度
            'articles' => Inertia::defer(function () use ($request) {
                // 每页条数限制，防止滥用
                $perPage = min((int) $request->input('per_page', 10), 50);

                // 构建基础查询并预加载关联
                $query = Article::query()
                    ->published()
                    ->with([
                        'author:id,name',
                        'categories:id,name,slug',
                        'tags:id,name,slug',
                    ]);

                // 按分类筛选
                if ($categoryId = $request->input('category_id')) {
                    $query->whereHas('categories', function ($q) use ($categoryId) {
                        $q->where('categories.id', $categoryId);
                    });
                }

                // 按标签筛选
                if ($tagId = $request->input('tag_id')) {
                    $query->whereHas('tags', function ($q) use ($tagId) {
                        $q->where('tags.id', $tagId);
                    });
                }

                // 模糊搜索
                if ($keyword = trim((string) $request->input('keyword'))) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('title', 'like', "%{$keyword}%")
                            ->orWhere('excerpt', 'like', "%{$keyword}%");
                    });
                }

                // 分页，并附带当前的 URL 查询参数
                return $query->latest('published_at')
                    ->paginate($perPage)
                    ->withQueryString();
            }),
        ]);
    }

    /**
     * 显示文章详情页
     *
     * @param Request $request
     * @param Article $article Laravel 路由模型绑定自动传入
     * @return Response
     */
    public function show(Request $request, Article $article, string $slug): Response
    {
        abort_if($article->slug !== $slug, 404);
        // 1. 状态校验：若文章未发布（草稿/已下架），直接抛出 404
        abort_if($article->status !== 1, 404);

        return Inertia::render('article/show', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '推荐文章', 'href' => route('articles.index')],
                ['title' => $article->title, 'href' => null],
            ],
            // 将耗时的文章主体及关联推荐查询包裹在 defer 延迟加载中
            'article' => Inertia::defer(function () use ($slug) {
                // 1. 查询文章实体
                $article = Article::where('slug', $slug)->firstOrFail();

                // 3. 增加浏览量
                $article->increment('views_count');

                // 4. 预加载关联属性
                $article->load([
                    'author:id,name',
                    'categories:id,name,slug',
                    'tags:id,name,slug',
                    'detail',
                ]);

                // 5. 延伸阅读推荐 (按分类查找 2 篇相关文章)
                $categoryIds = $article->categories->pluck('id');

                $relatedArticles = Article::query()
                    ->published()
                    ->where('id', '!=', $article->id)
                    ->whereHas('categories', fn($q) => $q->whereIn('categories.id', $categoryIds))
                    ->with(['author:id,name', 'categories:id,name,slug'])
                    ->latest('published_at')
                    ->take(2)
                    ->get();

                $article->setRelation('related_articles', $relatedArticles);

                return $article;
            }),
        ]);
    }
}
