<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;
use App\Models\Video;
use App\Models\Actor;
use App\Models\Channel;
use App\Models\Category;
use App\Models\Tag;

class SearchController extends Controller
{
    /**
     * 全局搜索页面（Inertia 渲染）
     */
    public function index(Request $request): Response
    {
        $query = trim((string) $request->input('q', ''));

        $results = [
            'videos' => [],
            'actors' => [],
            'channels' => [],
            'categories' => [],
        ];

        if (!empty($query)) {
            $results = $this->performSearch($query, 12);
        }

        return Inertia::render('search/index', [
            'query' => $query,
            'groupedResults' => $results,
        ]);
    }

    /**
     * 侧边栏/全局即时搜索接口（JSON 返回，供弹窗与自动补全使用）
     */
    public function global(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        $limit = max(1, min(10, (int) $request->input('limit', 5)));

        if (empty($query)) {
            return response()->json([
                'videos' => [],
                'actors' => [],
                'channels' => [],
                'categories' => [],
            ]);
        }

        $results = $this->performSearch($query, $limit);

        return response()->json($results);
    }

    /**
     * 执行四维 Meilisearch 聚合检索 (视频、演员、片商、分类)
     */
    private function performSearch(string $query, int $limit): array
    {
        // 1. 检索视频 (Video)
        try {
            $videos = Video::search($query)
                ->take($limit)
                ->get()
                ->map(fn (Video $v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'name_zh' => $v->name_zh,
                    'slug' => $v->slug,
                    'video_code' => $v->video_code,
                    'list_img' => $v->list_img,
                    'release_at' => $v->release_at,
                    'url' => route('videos.show', ['video' => $v->id, 'slug' => $v->slug ?: 'video']),
                ])
                ->all();
        } catch (\Throwable) {
            $videos = Video::query()
                ->where(function ($q) use ($query) {
                    $q->where('name', 'ILIKE', "%{$query}%")
                        ->orWhere('name_zh', 'ILIKE', "%{$query}%")
                        ->orWhere('video_code', 'ILIKE', "%{$query}%");
                })
                ->limit($limit)
                ->get()
                ->map(fn (Video $v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'name_zh' => $v->name_zh,
                    'slug' => $v->slug,
                    'video_code' => $v->video_code,
                    'list_img' => $v->list_img,
                    'release_at' => $v->release_at,
                    'url' => route('videos.show', ['video' => $v->id, 'slug' => $v->slug ?: 'video']),
                ])
                ->all();
        }

        // 2. 检索演员 (Actor)
        try {
            $actors = Actor::search($query)
                ->take($limit)
                ->get()
                ->map(fn (Actor $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'slug' => $a->slug,
                    'avatar' => $a->avatar,
                    'url' => route('actors.show', ['actor' => $a->id, 'slug' => $a->slug ?: 'actor']),
                ])
                ->all();
        } catch (\Throwable) {
            $actors = Actor::query()
                ->where('name', 'ILIKE', "%{$query}%")
                ->limit($limit)
                ->get()
                ->map(fn (Actor $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'slug' => $a->slug,
                    'avatar' => $a->avatar,
                    'url' => route('actors.show', ['actor' => $a->id, 'slug' => $a->slug ?: 'actor']),
                ])
                ->all();
        }

        // 3. 检索片商 (Channel)
        try {
            $channels = Channel::search($query)
                ->take($limit)
                ->get()
                ->map(fn (Channel $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'avatar' => $c->avatar,
                    'logo' => $c->logo,
                    'url' => route('channels.show', ['channel' => $c->id, 'slug' => $c->slug ?: 'channel']),
                ])
                ->all();
        } catch (\Throwable) {
            $channels = Channel::query()
                ->where('name', 'ILIKE', "%{$query}%")
                ->limit($limit)
                ->get()
                ->map(fn (Channel $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'avatar' => $c->avatar,
                    'logo' => $c->logo,
                    'url' => route('channels.show', ['channel' => $c->id, 'slug' => $c->slug ?: 'channel']),
                ])
                ->all();
        }

        // 4. 检索分类 (Category)
        try {
            $categories = Category::search($query)
                ->take($limit)
                ->get()
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'name_zh' => $c->name_zh,
                    'slug' => $c->slug,
                    'url' => route('categories.show', ['category' => $c->id, 'slug' => $c->slug ?: 'category']),
                ])
                ->all();
        } catch (\Throwable) {
            $categories = Category::query()
                ->where(function ($q) use ($query) {
                    $q->where('name', 'ILIKE', "%{$query}%")
                        ->orWhere('name_zh', 'ILIKE', "%{$query}%");
                })
                ->limit($limit)
                ->get()
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'name_zh' => $c->name_zh,
                    'slug' => $c->slug,
                    'url' => route('categories.show', ['category' => $c->id, 'slug' => $c->slug ?: 'category']),
                ])
                ->all();
        }

        return [
            'videos' => $videos,
            'actors' => $actors,
            'channels' => $channels,
            'categories' => $categories,
        ];
    }

    /**
     * 单独搜索演员接口（配合高级筛选等异步补全）
     */
    public function actors(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));

        if (empty($keyword)) {
            return response()->json([]);
        }

        try {
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
     * 单独搜索分类接口（配合筛选等异步补全）
     */
    public function categories(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));

        if (empty($keyword)) {
            return response()->json([]);
        }

        try {
            $categories = Category::search($keyword)->take(10)->get(['id', 'name', 'name_zh']);
            if ($categories->isEmpty()) {
                $categories = Category::query()
                    ->where(function ($q) use ($keyword) {
                        $q->where('name', 'ILIKE', "{$keyword}%")
                            ->orWhere('name_zh', 'ILIKE', "{$keyword}%");
                    })
                    ->select(['id', 'name', 'name_zh'])
                    ->limit(10)
                    ->get();
            }
        } catch (\Throwable) {
            $categories = Category::query()
                ->where(function ($q) use ($keyword) {
                    $q->where('name', 'ILIKE', "{$keyword}%")
                        ->orWhere('name_zh', 'ILIKE', "{$keyword}%");
                })
                ->select(['id', 'name', 'name_zh'])
                ->limit(10)
                ->get();
        }

        return response()->json($categories);
    }

    /**
     * 单独搜索标签接口
     */
    public function tags(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));

        if (empty($keyword)) {
            return response()->json([]);
        }

        $tags = Tag::query()
            ->where('name', 'ILIKE', "{$keyword}%")
            ->select(['id', 'name'])
            ->limit(10)
            ->get();

        return response()->json($tags);
    }
}
