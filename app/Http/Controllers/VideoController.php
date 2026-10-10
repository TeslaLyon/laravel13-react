<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Video;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Actor;
use App\Models\Channel;
use App\Models\VideoSubtitleRequest;
use Illuminate\Support\Sleep;
use Illuminate\Http\Request;
use App\Models\VideoSubtitle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use App\Services\VideoViewService;
use App\Services\VideoInteractionService;
use App\Services\ChannelSubscriptionService;


class VideoController extends Controller
{

    // TODO:大于 lg 列数问题
    // TODO:快捷推荐标签：系统自定义的、根据用户收藏 tag/category 读取，最近一周收藏量增幅最多的
    // 2026年08月05日14:19:31 还是改成系统写死的那种吧，比如，最新上架、最受欢迎、4K、VR、高清、中文字幕、无码
    public function index(Request $request): \Inertia\Response
    {
        // 1. 构建主视频列表查询
        $categoryName = $request->input('category');
        $actorsRaw = $request->input('actors');
        $tagsRaw = $request->input('tags');
        $channelsRaw = $request->input('channels');

        $query = Video::query()
            ->with('channel:id,name,slug,avatar,data_crawl_type')
            ->select([
                'id',
                'name',
                'name_zh',
                'slug',
                'channel_id',
                'list_img',
                'preview',
                'release_at',
                'is_4k',
                'is_vr',
                'likes_count',
                'favorites_count',
                'views_count',
                'created_at',
                'country'
            ]);

        // 应用筛选条件 (保持原有逻辑) ...
        if (!empty($categoryName) && $categoryName !== '全部') {
            $query->whereHas('categories', function ($q) use ($categoryName) {
                $q->where('name', $categoryName)->orWhere('slug', $categoryName);
            });
        }
        if (!empty($actorsRaw)) {
            $actorIds = array_filter(explode(',', $actorsRaw));
            if (!empty($actorIds)) {
                $query->whereHas('actors', function ($q) use ($actorIds) {
                    $q->whereIn('actors.id', $actorIds);
                });
            }
        }
        if (!empty($channelsRaw)) {
            $channelIds = array_filter(explode(',', $channelsRaw));
            if (!empty($channelIds)) {
                $query->whereIn('channel_id', $channelIds);
            }
        }

        $videos = $query->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('video/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '推荐视频', 'href' => null],
            ],
            'filters' => $request->only(['category', 'actors', 'tags', 'channels']),

            // 🎯 2. 使用 Inertia::defer 延迟加载横向快捷分类数组
            'quickFilters' => Inertia::defer(function () {
                $topCategories = Category::query()->orderByDesc('follow_num')->limit(2)->get(['id', 'name', 'slug']);
                $topTags = Tag::query()->orderByDesc('follow_num')->limit(2)->get(['id', 'name']);
                $topActors = Actor::query()->orderByDesc('follow_num')->limit(2)->get(['id', 'name']);

                $quickFilters = [
                    [
                        'id' => 'all',
                        'name' => '全部',
                        'type' => 'all',
                        'value' => '',
                    ]
                ];

                foreach ($topCategories as $cat) {
                    $quickFilters[] = [
                        'id' => 'cat_' . $cat->id,
                        'name' => $cat->name,
                        'type' => 'category',
                        'value' => $cat->name,
                    ];
                }

                foreach ($topTags as $tag) {
                    $quickFilters[] = [
                        'id' => 'tag_' . $tag->id,
                        'name' => $tag->name,
                        'type' => 'tag',
                        'value' => (string) $tag->id,
                    ];
                }

                foreach ($topActors as $actor) {
                    $quickFilters[] = [
                        'id' => 'actor_' . $actor->id,
                        'name' => $actor->name,
                        'type' => 'actor',
                        'value' => (string) $actor->id,
                    ];
                }

                return $quickFilters;
            }),

            'videos' => Inertia::defer(function () use ($videos) {
                return $videos;
            })
        ]);
    }

    // TODO:标签和分类在 show 页面中添加快捷关注功能，为后续猜你喜欢做准备
    public function show(Request $request, Video $video): \Inertia\Response
    {
        // TODO: 优化延迟关联查询
        // TODO：考虑：页面中增加修正历史记录展示功能
        // TODO:新增视频同款服装的卡片模块
        // TODO：如果不存在下载信息，用户也可以提交信息（增加申请按钮）
        // TODO：询问 AI 引入组件时多次传递的 video={video} 是否有性能问题？尽管只是使用了 video 里面的一个子数据，【按需传递】
        // TODO:list_img_large_meta字段即存储多种尺寸的，有存储单张图片的。

        // 1. 预加载必要的关联关系，避免 N+1 查询问题
        // $video->load([

        // ]);
        // dd($video->toArray());

        $actors = Actor::select('id', 'name', 'slug')->limit(30)->get();

        /** @var \App\Models\User|null $user */
        $user = $request->user();
        $isSubscribed = false;
        $isLike = false;
        $isDisLike = false;
        $isCollect = false;

        // 2. 记录视频有效浏览量（防抖 + Redis 内存原子缓冲，零写压力）
        $viewService = app(VideoViewService::class);
        $viewService->recordView($video, $request);
        $realViewsCount = $viewService->getViewCount($video);

        // 3. 如果用户已登录，获取其互动状态
        if ($user) {
            if ($user->isNotRegisteredAsLoveReacter()) {
                $user->registerAsLoveReacter();
            }
            if ($video->channel && $video->channel->isNotRegisteredAsLoveReactant()) {
                $video->channel->registerAsLoveReactant();
            }
            if ($video->isNotRegisteredAsLoveReactant()) {
                $video->registerAsLoveReactant();
            }

            $isSubscribed = $video->channel
                ? app(ChannelSubscriptionService::class)->getSubscriptionStatus($video->channel, $user)['is_subscribed']
                : false;
            $isLike = $video->viaLoveReactant()->isReactedBy($user, 'Like');
            $isDisLike = $video->viaLoveReactant()->isReactedBy($user, 'Dislike');
            $isCollect = $video->viaLoveReactant()->isReactedBy($user, "VideoCollect");
        }

        $channelSubscribersCount = $video->channel
            ? app(ChannelSubscriptionService::class)->getSubscribersCount($video->channel)
            : 0;

        // 4. 获取侧边栏推荐视频
        $recommendVideos = Video::with('channel:id,name,slug,avatar,data_crawl_type')
            ->orderByDesc('created_at')
            ->select('id', 'name', 'slug', 'channel_id', 'list_img', 'preview', 'release_at', 'is_4k', 'is_vr', 'likes_count', 'favorites_count', 'views_count', 'created_at')
            ->take(10)
            ->get();

        $categories = Category::select('id', 'name', 'slug')->limit(30)->get();
        $tags = Tag::select('id', 'name', 'slug')->limit(30)->get();

        // Sleep::for(2000)->milliseconds();

        // 4. 渲染 Inertia 页面
        return Inertia::render('video/show', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '推荐视频', 'href' => route('videos.index')],
                ['title' => $video->name, 'href' => null],
            ],
            // 使用 Inertia::defer 优化首屏加载速度
            'video' => Inertia::defer(fn() => Video::with([
                'videoDetail',
                'actors:id,name,slug,avatar,love_reactant_id',
                'tags:id,name,name_zh,slug',
                'channel:id,name,slug,avatar,love_reactant_id,data_crawl_type',
                'categories:id,name,name_zh,slug',
                'approvedSubtitles:id,video_id,user_id,language,file_path,file_size,is_external',
                'activeDownloads:id,video_id,title,type,cost_type,resolution,price,link,hash,file_size,extraction_code,archive_password,description,status,sort_order'
            ])->findOrFail($video->id)),
            'recommendVideos' => Inertia::defer(fn() => $recommendVideos),
            'isSubscribed' => $isSubscribed,
            'liked' => $isLike,
            'disLiked' => $isDisLike,
            'likeCount' => (int) ($video->likes_count ?? 0),
            'favoritesCount' => (int) ($video->favorites_count ?? 0),
            'viewsCount' => $realViewsCount,
            'channelSubscribersCount' => $channelSubscribersCount,
            'initialIsCollect' => $isCollect,
            'categories' => $categories,
            'tags' => $tags,
            'actors' => $actors,
        ]);
    }

    /**
     * 处理“点赞”逻辑
     */
    public function like(Request $request, Video $video, VideoInteractionService $interactionService)
    {
        $result = $interactionService->toggleLike($video, $request->user());

        return response()->json($result);
    }

    /**
     * 处理“踩”逻辑
     */
    public function dislike(Request $request, Video $video, VideoInteractionService $interactionService)
    {
        $result = $interactionService->toggleDislike($video, $request->user());

        return response()->json($result);
    }

    /**
     * 处理“收藏”逻辑
     */
    public function collect(Request $request, Video $video, VideoInteractionService $interactionService)
    {
        $result = $interactionService->toggleCollect($video, $request->user());

        return response()->json($result);
    }

    public function saveToWatchLater(Request $request, Video $video)
    {
        $user = $request->user();
        $reacter = $user->viaLoveReacter();

        $isCollect = false;
        $msg = '操作成功';

        if ($reacter->hasReactedTo($video, "SaveToWatchLater")) {
            $reacter->unreactTo($video, "SaveToWatchLater");
            $isCollect = false;
            $msg = '已取消保存到“稍后再看”';
        } else {
            $reacter->reactTo($video, "SaveToWatchLater");
            $isCollect = true;
            $msg = '已保存到“稍后再看”';
        }

        Sleep::for(2000)->milliseconds();
        return response()->json([
            'status' => $isCollect,
            'message' => $msg,
        ]);
    }

    public function menuStatus(Request $request, Video $video)
    {
        $user = $request->user();
        $isCollect = false;
        $isSaveToWatchLater = false;
        if ($user) {
            $isSaveToWatchLater = $video->viaLoveReactant()->isReactedBy($user, 'SaveToWatchLater');
            $isCollect = $video->viaLoveReactant()->isReactedBy($user, "VideoCollect");
        }
        // Sleep::for(2000)->milliseconds();
        return response()->json([
            'isCollect' => $isCollect,
            'isSaveToWatchLater' => $isSaveToWatchLater,
        ]);
    }

    /**
     * 处理用户上传字幕信息（文件或链接）
     */
    public function subtitleUpload(Request $request, Video $video)
    {
        // 1. 动态表单验证
        $validated = $request->validate([
            'upload_type' => 'required|in:file,url',
            'title' => 'nullable|string|max:100',
            // 文件模式：必须有文件，且最大 5MB
            'file' => 'required_if:upload_type,file|nullable|file|max:5120',
            // 链接模式：必须有 URL，且格式正确
            'url' => 'required_if:upload_type,url|nullable|url|max:1024',
        ], [
            'file.required_if' => '请选择一个字幕文件。',
            'file.max' => '字幕文件大小不能超过 5MB。',
            'url.required_if' => '请填写字幕文件的下载链接。',
            'url.url' => '请填写有效的网址（如以 http:// 或 https:// 开头）。',
        ]);

        $filePath = null;
        $sourceUrl = null;
        $extension = null;
        $fileSize = null;

        // 2. 根据提交类型处理数据
        if ($request->upload_type === 'file') {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedExtensions = ['srt', 'ass', 'ssa', 'vtt', 'sub', 'idx', 'sbv', 'stl'];

            if (!in_array($extension, $allowedExtensions)) {
                return back()->withErrors(['file' => '不支持的字幕格式，仅支持：' . implode(', ', $allowedExtensions)]);
            }

            // 存储本地文件到 storage/app/public/subtitles/{video_id}/ 目录下
            $filePath = $file->store("subtitles/{$video->id}", 'public');
            $fileSize = $file->getSize();
            $isExternal = false;
        } else {
            // 链接模式
            $sourceUrl = $request->input('url');
            $isExternal = true;

            // 解析 URL 路径尝试提取后缀名
            $parsedPath = parse_url($sourceUrl, PHP_URL_PATH);
            if ($parsedPath) {
                $ext = pathinfo($parsedPath, PATHINFO_EXTENSION);
                if ($ext) {
                    $extension = strtolower($ext);
                }
            }
        }

        // 3. 写入数据库记录
        VideoSubtitle::create([
            'video_id' => $video->id,
            'user_id' => Auth::id(),
            'title' => $validated['title'] ?? null,
            'language' => 'zh_CN',
            'file_path' => $filePath,   // 本地路径 (仅在文件模式下有值)
            'url' => $sourceUrl,  // 网络外链 (仅在链接模式下有值)
            'is_external' => $isExternal, // 外部链接标识
            'format' => $extension,
            'file_size' => $fileSize,
            'status' => 1,   // 默认进入待审核状态
        ]);

        // 4. 返回 JSON 响应
        return response()->json([
            'success' => true,
            'message' => '提交成功，感谢您的贡献！',
        ], 200);
    }

    /**
     * 获取字幕的下载/访问链接 (返回 JSON 给前端处理)
     *
     * @param Video $video
     * @param string $slug
     * @param VideoSubtitle $subtitle
     * @return \Illuminate\Http\JsonResponse
     */
    public function subtitleDownload(Video $video, string $slug, VideoSubtitle $subtitle)
    {
        // 1. 校验字幕与视频的隶属关系及审核状态
        if ($subtitle->video_id !== $video->id || $subtitle->status !== 'approved') {
            return response()->json([
                'message' => '指定的字幕不存在或尚未通过审核。'
            ], 404);
        }

        Sleep::for(3000)->milliseconds();

        // 2. 处理外部网络链接模式
        if ($subtitle->is_external || !empty($subtitle->url)) {
            $downloadUrl = $subtitle->url ?? $subtitle->file_path;

            if (!filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'message' => '字幕下载链接格式无效。'
                ], 400);
            }

            return response()->json([
                'type' => 'url',
                'url' => $downloadUrl,
            ]);
        }

        // 3. 处理本地服务器存储文件模式
        $filePath = $subtitle->file_path;

        if (!$filePath || !Storage::disk('public')->exists($filePath)) {
            return response()->json([
                'message' => '字幕文件物理路径不存在或已被删除，请联系管理员。'
            ], 404);
        }

        // 获取 Storage 磁盘公开可访问的完整静态 URL
        $fileUrl = Storage::disk('public')->url($filePath);

        return response()->json([
            'type' => 'file',
            'url' => $fileUrl,
        ]);
    }

    /**
     * 处理用户提交的“求字幕”申请
     * TODO: 当存在多个字幕文件时该如何处理？再添加一个 uuid？请求时卸载这个 id？
     *
     * @param Request $request
     * @param Video $video
     * @return \Illuminate\Http\JsonResponse
     */
    public function subtitleRequest(Request $request, Video $video)
    {
        // 使用 firstOrCreate 防止同一个用户对同一个视频发起重复的申请。
        // 第一个数组是“查找条件”，第二个数组是“如果没找到，创建时需要额外补充的数据”。
        VideoSubtitleRequest::firstOrCreate(
            [
                'video_id' => $video->id,
                'user_id' => \Illuminate\Support\Facades\Auth::id(),
            ],
            [
                'status' => 1
            ]
        );

        // 记录完成后，重定向回上一页。Inertia 会处理这个响应并触发前端的 onSuccess 回调。
        return response()->json([
            'success' => true,
            'message' => '提交成功，感谢您的贡献！'
        ], 200);
    }

    /**
     * 获取动态级联筛选选项列表
     *
     * TODO：默认读取热门的 category/tag actor channel，后续可以考虑根据用户的观看历史、收藏、点赞等行为来动态调整推荐的筛选选项。
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getCascadeFilters(Request $request): JsonResponse
    {
        $selectedActors = $request->input('actors', []);
        $selectedMixedTags = $request->input('tags', []);
        $selectedChannels = $request->input('channels', []);
        $actorSearch = trim((string) $request->input('actor_search', ''));

        $isDefaultRequest = empty($selectedActors) && empty($selectedMixedTags) && empty($selectedChannels) && empty($actorSearch);

        if ($isDefaultRequest) {
            // 🎯 缓存键名保持一致
            $defaultData = Cache::remember('video_filters_default_data_v2', 86400, function () {
                return $this->fetchDefaultFilterData();
            });

            return response()->json($defaultData);
        }

        return response()->json($this->fetchDynamicCascadeData($selectedActors, $selectedMixedTags, $selectedChannels, $actorSearch));
    }

    /**
     * 提取默认的筛选元数据（修复版：确保存入 Redis 的是纯数组）
     */
    private function fetchDefaultFilterData(): array
    {
        // 🎯 优先展示有作品或热门的演员，不足时自动兜底补充
        $actors = Actor::whereHas('videos')
            ->orderByDesc('follow_num')
            ->select('id', 'name')
            ->limit(50)
            ->get()
            ->toArray();

        if (count($actors) < 20) {
            $fallback = Actor::select('id', 'name')->limit(50)->get()->toArray();
            $existingIds = array_column($actors, 'id');
            foreach ($fallback as $fa) {
                if (!in_array($fa['id'], $existingIds)) {
                    $actors[] = $fa;
                }
            }
        }

        $channels = Channel::orderByDesc('created_at')->select('id', 'name')->limit(50)->get()->toArray();
        if (count($channels) < 10) {
            $channels = Channel::select('id', 'name')->limit(50)->get()->toArray();
        }

        $categories = Category::select('id', 'name', 'name_zh')->get()->map(function ($item) {
            return [
                'id' => 'cat_' . $item->id,
                'name' => !empty($item->name_zh) ? "{$item->name} ({$item->name_zh})" : $item->name,
            ];
        });

        $tags = Tag::select('id', 'name', 'name_zh')->limit(50)->get()->map(function ($item) {
            return [
                'id' => 'tag_' . $item->id,
                'name' => !empty($item->name_zh) ? "{$item->name} ({$item->name_zh})" : $item->name,
            ];
        });

        // 🎯 使用 ->all() 或 ->toArray() 确保合并后的 Collection 转换为纯数组
        $mergedTags = $categories->concat($tags)->values()->all();

        // 🎯 保证传给 Redis 缓存的每一个字段都是纯 PHP 数组
        return [
            'actors' => $actors,
            'tags' => $mergedTags,
            'channels' => $channels,
        ];
    }

    private function fetchDynamicCascadeData(array $selectedActors, array $selectedMixedTags, array $selectedChannels, string $actorSearch = ''): array
    {
        $selectedCatIds = [];
        $selectedTagIds = [];

        foreach ($selectedMixedTags as $rawId) {
            if (str_starts_with($rawId, 'cat_')) {
                $selectedCatIds[] = (int) str_replace('cat_', '', $rawId);
            } elseif (str_starts_with($rawId, 'tag_')) {
                $selectedTagIds[] = (int) str_replace('tag_', '', $rawId);
            } else {
                $selectedTagIds[] = (int) $rawId;
            }
        }

        $videoQuery = Video::query();

        if (!empty($selectedActors)) {
            $videoQuery->whereHas('actors', function ($q) use ($selectedActors) {
                $q->whereIn('actors.id', $selectedActors);
            });
        }

        if (!empty($selectedChannels)) {
            $videoQuery->whereIn('channel_id', $selectedChannels);
        }

        if (!empty($selectedCatIds)) {
            $videoQuery->whereHas('categories', function ($q) use ($selectedCatIds) {
                $q->whereIn('categories.id', $selectedCatIds);
            });
        }

        if (!empty($selectedTagIds)) {
            $videoQuery->whereHas('tags', function ($q) use ($selectedTagIds) {
                $q->whereIn('tags.id', $selectedTagIds);
            });
        }

        $hasVideoFilters = !empty($selectedActors) || !empty($selectedChannels) || !empty($selectedCatIds) || !empty($selectedTagIds);

        // 1. 动态检索或级联获取可用演员
        $actorQuery = Actor::query();
        if ($hasVideoFilters) {
            $matchingVideoIds = $videoQuery->pluck('id');
            $actorQuery->whereHas('videos', function ($q) use ($matchingVideoIds) {
                $q->whereIn('videos.id', $matchingVideoIds);
            });
        }
        if (!empty($actorSearch)) {
            try {
                // 🚀 优先使用 Meilisearch 进行毫秒级错别字容错模糊检索
                $meiliActorIds = Actor::search($actorSearch)->take(50)->keys()->all();
                if (!empty($meiliActorIds)) {
                    $actorQuery->whereIn('id', $meiliActorIds);
                    $sanitizedIds = implode(',', array_map('intval', $meiliActorIds));
                    $actorQuery->orderByRaw("array_position(ARRAY[{$sanitizedIds}]::bigint[], id)");
                } else {
                    $actorQuery->where('name', 'ILIKE', "%{$actorSearch}%");
                }
            } catch (\Throwable) {
                // 异常或本地未启动 Meilisearch 时安全优雅降级回数据库 ILIKE
                $actorQuery->where('name', 'ILIKE', "%{$actorSearch}%");
            }
        }
        $availableActors = $actorQuery->select('id', 'name')->limit(50)->get()->toArray();

        // 🎯 确保用户已选中的演员始终保留在列表顶端，绝不丢失勾选状态
        if (!empty($selectedActors)) {
            $existingSelected = Actor::whereIn('id', $selectedActors)->select('id', 'name')->get()->toArray();
            $currentActorIds = array_column($availableActors, 'id');
            foreach ($existingSelected as $selActor) {
                if (!in_array($selActor['id'], $currentActorIds)) {
                    array_unshift($availableActors, $selActor);
                }
            }
        }

        // 2. 获取片商与标签的级联数据
        if ($hasVideoFilters) {
            if (!isset($matchingVideoIds)) {
                $matchingVideoIds = $videoQuery->pluck('id');
            }

            $availableChannels = Channel::whereHas('videos', function ($q) use ($matchingVideoIds) {
                $q->whereIn('videos.id', $matchingVideoIds);
            })->select('id', 'name')->get()->toArray();

            $availableCategories = Category::whereHas('videos', function ($q) use ($matchingVideoIds) {
                $q->whereIn('videos.id', $matchingVideoIds);
            })->select('id', 'name', 'name_zh')->get()->map(function ($item) {
                return [
                    'id' => 'cat_' . $item->id,
                    'name' => !empty($item->name_zh) ? "{$item->name} ({$item->name_zh})" : $item->name,
                ];
            });

            $availableTags = Tag::whereHas('videos', function ($q) use ($matchingVideoIds) {
                $q->whereIn('videos.id', $matchingVideoIds);
            })->select('id', 'name', 'name_zh')->get()->map(function ($item) {
                return [
                    'id' => 'tag_' . $item->id,
                    'name' => !empty($item->name_zh) ? "{$item->name} ({$item->name_zh})" : $item->name,
                ];
            });
        } else {
            $defaultData = $this->fetchDefaultFilterData();
            return [
                'actors' => $availableActors,
                'tags' => $defaultData['tags'],
                'channels' => $defaultData['channels'],
            ];
        }

        return [
            'actors' => $availableActors,
            'tags' => $availableCategories->concat($availableTags)->values()->all(),
            'channels' => $availableChannels,
        ];
    }
}
