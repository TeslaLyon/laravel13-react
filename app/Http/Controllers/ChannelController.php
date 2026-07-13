<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Channel;
use Illuminate\Support\Sleep;

class ChannelController extends Controller
{
    public function index()
    {
        $channels = Channel::orderByDesc('created_at')->paginate(1);
        // dd($channels->toArray());

        return Inertia::render('channel/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '片商', 'href' => null], // 当前页没有 URL
            ],
            'channels' => Inertia::defer(function () use ($channels) {
                // Sleep::for(2000)->milliseconds();
                return $channels;
            })
        ]);
    }

    public function show(Channel $channel)
    {
        return Inertia::render('channel/show', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '片商', 'href' => route('channels.index')],
                ['title' => $channel->name, 'href' => null], // 当前页没有 URL
            ],
            'channel' => [
                'id' => 1,
                'name' => '星空视觉工作室 (Starry Studio)',
                'avatar_url' => 'https://api.dicebear.com/9.x/bottts/svg?seed=Starry&backgroundColor=b6e3f4',
                'banner_url' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?q=80&w=2564&auto=format&fit=crop',
                'followers_count' => '125万',
                'handle' => '@StarryStudio',
                'description' => '致力于创作最高质量的视觉作品和短片。'
            ],
            'videos' => [
                [
                    'id' => 101,
                    'title' => '【4K画质】探索未知宇宙的奥秘...',
                    'thumbnail_url' => 'https://images.unsplash.com/photo-1462331940025-496dfbfc7564?w=800&auto=format&fit=crop&q=60',
                    'views' => '23.5万',
                    'published_at' => '2天前'
                ],
                // ... 其他视频
            ]
            // 'video' => Inertia::defer(function () use ($video) {
            //     Sleep::for(1000)->milliseconds();
            //     return $video;
            // }),
            // 'isSubscribed' => $isSubscribed,
            // 'liked' => $isLike,
            // 'disLiked' => $isDisLike,
            // 'likeCount' => 975,
            // 'initialIsCollect' => $isCollect,
        ]);
    }

    /**
     * 处理订阅与取消订阅的切换
     */
    public function toggleSubscribe(Request $request, Channel $channel)
    {
        // 1. 获取当前登录的用户实例
        /** @var \App\Models\User $user */
        $user = $request->user();
        $reacter = $user->viaLoveReacter();

        // 定义一个变量来记录最终的订阅状态
        $isSubscribed = false;

        if ($reacter->hasReactedTo($channel, "SubscribeChannel")) {
            $reacter->unreactTo($channel, "SubscribeChannel");
            $isSubscribed = false; // 取消订阅后，状态为 false
        } else {
            $reacter->reactTo($channel, "SubscribeChannel");
            $isSubscribed = true;  // 订阅后，状态为 true
        }

        Sleep::for(2000)->milliseconds();
        // 返回 JSON 数据，供前端精细化控制
        return response()->json([
            'is_subscribed' => $isSubscribed,
            'message' => $isSubscribed ? '订阅成功' : '已取消订阅'
        ]);
    }

    public function subscribeStatus(Request $request, Channel $channel)
    {
        $user = $request->user();

        // 1. 如果用户未登录，直接返回未关注
        if (!$user) {
            return response()->json([
                'is_subscribed' => false
            ]);
        }

        // 2. 获取 Laravel Love 的 Reacter 实例
        $reacter = $user->viaLoveReacter();

        // 3. 检查是否已经存在类型为 'Subscribe' 的 Reaction
        $isSubscribed = $reacter->hasReactedTo($channel, 'SubscribeChannel');
        Sleep::for(2000)->milliseconds();
        // 4. 返回 JSON 数据供前端读取
        return response()->json([
            'is_subscribed' => $isSubscribed,
            'message' => $isSubscribed ? '订阅成功' : '已取消订阅'
        ]);
    }
}
