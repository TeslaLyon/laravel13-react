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
            $videoModels = Video::search($query)->take($limit)->get();
            if ($videoModels->isEmpty()) {
                $videoModels = Video::query()
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'ILIKE', "%{$query}%")
                            ->orWhere('name_zh', 'ILIKE', "%{$query}%")
                            ->orWhere('video_code', 'ILIKE', "%{$query}%")
                            ->orWhere('slug', 'ILIKE', "%{$query}%")
                            ->orWhereHas('downloads', function ($dq) use ($query) {
                                $dq->where('title', 'ILIKE', "%{$query}%")
                                    ->orWhere('hash', 'ILIKE', "%{$query}%");
                            });
                    })
                    ->limit($limit)
                    ->get();
            }
        } catch (\Throwable) {
            $videoModels = Video::query()
                ->where(function ($q) use ($query) {
                    $q->where('name', 'ILIKE', "%{$query}%")
                        ->orWhere('name_zh', 'ILIKE', "%{$query}%")
                        ->orWhere('video_code', 'ILIKE', "%{$query}%")
                        ->orWhere('slug', 'ILIKE', "%{$query}%")
                        ->orWhereHas('downloads', function ($dq) use ($query) {
                            $dq->where('title', 'ILIKE', "%{$query}%")
                                ->orWhere('hash', 'ILIKE', "%{$query}%");
                        });
                })
                ->limit($limit)
                ->get();
        }

        $videoModels->loadMissing('channel:id,name,slug,avatar,data_crawl_type');

        $videos = $videoModels->map(fn (Video $v) => [
            'id' => $v->id,
            'name' => $v->name,
            'name_zh' => $v->name_zh,
            'slug' => $v->slug,
            'video_code' => $v->video_code,
            'channel_id' => $v->channel_id,
            'list_img' => $v->list_img,
            'preview' => $v->preview,
            'release_at' => $v->release_at,
            'country' => $v->country,
            'is_4k' => (bool) $v->is_4k,
            'is_vr' => (bool) $v->is_vr,
            'likes_count' => $v->likes_count,
            'favorites_count' => $v->favorites_count,
            'created_at' => $v->created_at?->toISOString() ?? (string) $v->created_at,
            'url' => route('videos.show', ['video' => $v->id, 'slug' => $v->slug ?: 'video']),
            'channel' => $v->channel ? [
                'id' => $v->channel->id,
                'name' => $v->channel->name,
                'slug' => $v->channel->slug,
                'avatar' => $v->channel->avatar,
                'data_crawl_type' => $v->channel->data_crawl_type,
            ] : null,
        ])->all();

        // 2. 检索演员 (Actor)
        try {
            $actorModels = Actor::search($query)->take($limit)->get();
            if ($actorModels->isEmpty()) {
                $actorModels = Actor::query()
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'ILIKE', "%{$query}%")
                            ->orWhere('slug', 'ILIKE', "%{$query}%");
                    })
                    ->limit($limit)
                    ->get();
            }
        } catch (\Throwable) {
            $actorModels = Actor::query()
                ->where(function ($q) use ($query) {
                    $q->where('name', 'ILIKE', "%{$query}%")
                        ->orWhere('slug', 'ILIKE', "%{$query}%");
                })
                ->limit($limit)
                ->get();
        }

        $actors = $actorModels->map(fn (Actor $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'slug' => $a->slug,
            'avatar' => $a->avatar,
            'url' => route('actors.show', ['actor' => $a->id, 'slug' => $a->slug ?: 'actor']),
        ])->all();

        // 3. 检索片商 (Channel)
        try {
            $channelModels = Channel::search($query)->take($limit)->get();
            if ($channelModels->isEmpty()) {
                $channelModels = Channel::query()
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'ILIKE', "%{$query}%")
                            ->orWhere('slug', 'ILIKE', "%{$query}%");
                    })
                    ->limit($limit)
                    ->get();
            }
        } catch (\Throwable) {
            $channelModels = Channel::query()
                ->where(function ($q) use ($query) {
                    $q->where('name', 'ILIKE', "%{$query}%")
                        ->orWhere('slug', 'ILIKE', "%{$query}%");
                })
                ->limit($limit)
                ->get();
        }

        $channels = $channelModels->map(fn (Channel $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'avatar' => $c->avatar,
            'logo' => $c->logo,
            'url' => route('channels.show', ['channel' => $c->id, 'slug' => $c->slug ?: 'channel']),
        ])->all();

        // 4. 检索分类 (Category)
        try {
            $categoryModels = Category::search($query)->take($limit)->get();
            if ($categoryModels->isEmpty()) {
                $categoryModels = Category::query()
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'ILIKE', "%{$query}%")
                            ->orWhere('name_zh', 'ILIKE', "%{$query}%")
                            ->orWhere('slug', 'ILIKE', "%{$query}%");
                    })
                    ->limit($limit)
                    ->get();
            }
        } catch (\Throwable) {
            $categoryModels = Category::query()
                ->where(function ($q) use ($query) {
                    $q->where('name', 'ILIKE', "%{$query}%")
                        ->orWhere('name_zh', 'ILIKE', "%{$query}%")
                        ->orWhere('slug', 'ILIKE', "%{$query}%");
                })
                ->limit($limit)
                ->get();
        }

        $categories = $categoryModels->map(fn (Category $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'name_zh' => $c->name_zh,
            'slug' => $c->slug,
            'url' => route('categories.show', ['category' => $c->id, 'slug' => $c->slug ?: 'category']),
        ])->all();

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
