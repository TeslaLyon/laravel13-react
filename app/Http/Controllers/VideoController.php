<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Video;
use Illuminate\Support\Sleep;
use Illuminate\Http\Request;

class VideoController extends Controller
{

    public function index(): \Inertia\Response
    {
        $videos = Video::with('channel:id,name,slug,avatar')
            ->orderByDesc('created_at')
            ->select('id', 'name', 'slug', 'channel_id', 'list_img', 'preview', 'release_at', 'is_4k', 'is_vr', 'likes_count', 'favorites_count', 'created_at')
            ->paginate(9);
        // dd($videos->toArray());
        return Inertia::render('video/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '推荐视频1111', 'href' => null], // 当前页没有 URL
            ],
            'videos' => Inertia::defer(function () use ($videos) {
                // Sleep::for(1000)->milliseconds();
                return $videos;
            })
        ]);
    }

    public function show(Request $request, Video $video): \Inertia\Response
    {
        $video->load('channel');
        // dd($video->toArray());
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        $isSubscribed = false;
        $isLike = false;
        $isDisLike = false;
        $isCollect = false;
        if ($user) {
            // 使用视角 2：从 channel 视角出发，检查是否被当前用户订阅
            $isSubscribed = $video->channel->viaLoveReactant()->isReactedBy($user, 'SubscribeChannel');
            $isLike = $video->viaLoveReactant()->isReactedBy($user, 'Like');
            $isDisLike = $video->viaLoveReactant()->isReactedBy($user, 'Dislike');
            $isCollect = $video->viaLoveReactant()->isReactedBy($user, "VideoCollect");
        }
        $recommendVideos = Video::with('channel:id,name,slug,avatar')
            ->orderByDesc('created_at')
            ->select('id', 'name', 'slug', 'channel_id', 'list_img', 'preview', 'release_at', 'is_4k', 'is_vr', 'likes_count', 'favorites_count', 'created_at')
            ->take(10)
            ->get();
        return Inertia::render('video/show', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '推荐视频', 'href' => route('videos.index')],
                ['title' => $video->name, 'href' => null], // 当前页没有 URL
            ],
            'video' => Inertia::defer(function () use ($video) {
                Sleep::for(1000)->milliseconds();
                return $video;
            }),
            'recommendVideos' => Inertia::defer(function () use ($recommendVideos) {
                return $recommendVideos;
            }),
            'isSubscribed' => $isSubscribed,
            'liked' => $isLike,
            'disLiked' => $isDisLike,
            'likeCount' => 975,
            'initialIsCollect' => $isCollect,
        ]);
    }

    /**
     * 处理“点赞”逻辑
     */
    public function like(Request $request, Video $video)
    {
        // 1. 获取当前登录用户并转化为 "反应者 (Reacter)"
        $reacter = $request->user()->viaLoveReacter();

        // 2. 互斥处理：如果用户之前“踩 (Dislike)”过这个视频，先撤销“踩”
        if ($reacter->hasReactedTo($video, 'Dislike')) {
            $reacter->unreactTo($video, 'Dislike');
        }

        // 3. 切换处理：如果用户已经“点赞 (Like)”过，说明这次点击是为了“取消点赞”
        if ($reacter->hasReactedTo($video, 'Like')) {
            $reacter->unreactTo($video, 'Like');

            return response()->json([
                'status' => 'unliked',
                'message' => '已取消点赞'
            ]);
        }

        // 4. 正常点赞
        $reacter->reactTo($video, 'Like');

        return response()->json([
            'status' => 'liked',
            'message' => '点赞成功'
        ]);
    }

    /**
     * 处理“踩”逻辑
     */
    public function dislike(Request $request, Video $video)
    {
        $reacter = $request->user()->viaLoveReacter();

        // 互斥处理：如果用户之前“点赞”过，先撤销“点赞”
        if ($reacter->hasReactedTo($video, 'Like')) {
            $reacter->unreactTo($video, 'Like');
        }

        // 切换处理：如果用户已经“踩”过，说明这次点击是为了“取消踩”
        if ($reacter->hasReactedTo($video, 'Dislike')) {
            $reacter->unreactTo($video, 'Dislike');

            return response()->json([
                'status' => 'undisliked',
                'message' => '已取消踩'
            ]);
        }

        // 正常踩
        $reacter->reactTo($video, 'Dislike');

        return response()->json([
            'status' => 'disliked',
            'message' => '踩成功'
        ]);
    }

    public function collect(Request $request, Video $video)
    {
        $user = $request->user();
        $reacter = $user->viaLoveReacter();

        $isCollect = false;
        $msg = '操作成功';

        if ($reacter->hasReactedTo($video, "VideoCollect")) {
            $reacter->unreactTo($video, "VideoCollect");
            $isCollect = false;
            $msg = '已取消收藏';
        } else {
            $reacter->reactTo($video, "VideoCollect");
            $isCollect = true;
            $msg = '收藏成功';
        }

        Sleep::for(2000)->milliseconds();
        return response()->json([
            'status' => $isCollect,
            'message' => $msg,
        ]);
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
}
