<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VideoSubtitleFeedback;
use App\Models\VideoSubtitle;
use App\Models\Video;

class VideoSubtitleFeedbackController extends Controller
{
    /**
     * 接收并存储字幕反馈 (配合 Inertia)
     */
    public function store(Request $request, Video $video, string $slug, VideoSubtitle $subtitle, VideoSubtitleFeedback $subtitleFeedback)
    {
        // 1. 数据验证
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ], [
            'content.required' => '反馈内容不能为空。',
            'content.max' => '反馈内容不能超过 1000 个字符。',
        ]);

        // 2. 写入数据库
        VideoSubtitleFeedback::create([
            'video_subtitle_id' => $subtitle->id,
            'user_id' => $request->user() ? $request->user()->id : null,
            'content' => $validated['content'],
            'status' => 'pending',
        ]);

        // 3. 【核心修复】：返回 back() 重定向，而不是 JSON。
        // Inertia 会拦截这个响应，触发前端的 onSuccess 回调。
        return response()->json([
            'success' => true,
            'message' => '提交成功，感谢您的贡献！'
        ], 200);
    }
}
