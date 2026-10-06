<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Channel;
use Illuminate\Support\Sleep;
use App\Models\User;
use App\Services\ChannelSubscriptionService;

class ChannelController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->input('category', '全部片商');

        return Inertia::render('channel/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '片商', 'href' => null],
            ],
            // 将当前选中的分类传回前端
            'currentCategory' => $category,
            // 🌟 使用 Inertia::defer 延迟加载数据库分页查询
            'channels' => Inertia::defer(function () use ($request, $category) {
                $query = Channel::query()->orderByDesc('created_at');

                // 当分类不是“全部片商”时，增加条件筛选
                if ($category && $category !== '全部片商') {
                    $query->where('category', $category);
                }

                // 每页 12 条数据，withQueryString() 确保翻页时保留 category 参数
                return $query->paginate(12)->withQueryString();
            })
        ]);
    }

    public function show(Request $request, Channel $channel, string $slug, ChannelSubscriptionService $subscriptionService, string $tab = 'home')
    {
        abort_if($channel->slug !== $slug, 404);

        /** @var User|null $currentUser */
        $currentUser = $request->user();

        // 1. 获取当前用户对该片商的真实订阅状态与通知偏好（单条带索引高能 SQL，涵盖快捷订阅与偏好订阅）
        $subscriptionStatus = $subscriptionService->getSubscriptionStatus($channel, $currentUser);
        $isSubscribed = $subscriptionStatus['is_subscribed'];

        // 2. 计算实体的总订阅人数（从数据库基数 + Redis 增量高效获取）
        $subscribersCount = $subscriptionService->getSubscribersCount($channel);

        // ==========================================
        // 3. 数据结构拍平处理，适配前端 BaseDetailShow 组件
        // ==========================================
        $channelData = $channel->toArray();
        $channelData['subscribersCount'] = $subscribersCount;
        $channelData['follow_num'] = $subscribersCount;
        if ($channel->detail) {
            $channelData['basic_info'] = $channel->detail->basic_info ?? [];
            $channelData['physical_info'] = $channel->detail->physical_info ?? [];
            $channelData['socials'] = $channel->detail->socials ?? [];
            unset($channelData['detail']);
        } else {
            $channelData['basic_info'] = [];
            $channelData['physical_info'] = [];
            $channelData['socials'] = [];
        }

        $channelData['notificationType'] = $subscriptionStatus['notification_type'];

        // ==========================================
        // 5. 渲染前端 Inertia 组件并使用 defer 延迟加载大体积数据
        // ==========================================
        return Inertia::render('channel/show', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '片商', 'href' => route('channels.index')],
                ['title' => $channel->name, 'href' => null],
            ],
            'channel' => $channelData,
            'subscribersCount' => $subscribersCount,
            'isSubscribed' => $isSubscribed,
            'currentTab' => $tab,

            // 🌟 首页 Tab：最新视频（延迟加载，并关联预加载演员数据）
            'latestVideos' => Inertia::defer(function () use ($channel) {
                $videos = $channel->videos()->latest()->take(3)->get();

                // 在内存中将现有 $channel 挂载到每个 video 上
                $videos->each(fn($video) => $video->setRelation('channel', $channel));

                return $videos;
            }),

            // 🌟 首页 Tab：最新图片（延迟加载）
            // 'latestPhotos' => Inertia::defer(
            //     fn() => $channel->images()
            //         ->latest()
            //         ->take(5)
            //         ->get()
            // ),
            'latestPhotos' => [],

            // 🌟 视频 Tab：分页视频列表（延迟加载）
            'paginatedVideos' => Inertia::defer(function () use ($channel) {
                $paginator = $channel->videos()->latest()->paginate(12)->withQueryString();

                // 对分页数据集的当前页集合在内存中绑定 $channel
                $paginator->getCollection()->each(fn($video) => $video->setRelation('channel', $channel));

                return $paginator;
            }),

            // 🌟 图片 Tab：分页图片列表（延迟加载）
            // 'paginatedPhotos' => Inertia::defer(
            //     fn() => $channel->images()
            //         ->latest()
            //         ->paginate(15)
            //         ->withQueryString()
            // ),
            'paginatedPhotos' => [],
        ]);
    }

    /**
     * 处理订阅与取消订阅的切换
     */
    public function toggleSubscribe(Request $request, Channel $channel, ChannelSubscriptionService $subscriptionService)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $subscriptionService->toggleSubscribe($channel, $user);

        return response()->json($result);
    }

    public function subscribeStatus(Request $request, Channel $channel, ChannelSubscriptionService $subscriptionService)
    {
        $status = $subscriptionService->getSubscriptionStatus($channel, $request->user());

        return response()->json([
            'is_subscribed' => $status['is_subscribed'],
            'notification_type' => $status['notification_type'],
            'message' => $status['is_subscribed'] ? '已订阅' : '未订阅',
            'subscribers_count' => $subscriptionService->getSubscribersCount($channel),
        ]);
    }
}
