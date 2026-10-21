<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;
use App\Models\VideoDownload;
use App\Models\VideoDownloadFeedback;
use App\Enums\VideoDownloadFeedbackStatus;
use App\Enums\VideoDownloadFeedbackType;
use Illuminate\Validation\Rule;

class VideoDownloadFeedbackController extends Controller
{
    /**
     * 接收并存储视频磁力/下载资源的纠错反馈
     */
    public function store(Request $request, Video $video, string $slug, VideoDownload $download)
    {
        // 1. 安全校验：确保下载资源从属于当前视频
        if ((int) $download->video_id !== (int) $video->id) {
            return response()->json([
                'success' => false,
                'message' => '资源与视频不匹配',
            ], 404);
        }

        // 2. 数据有效性验证
        $validTypes = array_map(fn($case) => $case->value, VideoDownloadFeedbackType::cases());

        $validated = $request->validate([
            'type' => ['required', 'integer', Rule::in($validTypes)],
            'content' => ['nullable', 'string', 'max:1000'],
        ], [
            'type.required' => '请选择反馈的问题类型。',
            'type.in' => '所选的问题类型无效。',
            'content.max' => '详细描述不能超过 1000 个字符。',
        ]);

        $user = $request->user();
        $userId = $user?->id;

        // 3. 防重复刷单校验：同一用户在 5 分钟内对同一资源不允许重复提交待处理反馈
        $existsRecent = VideoDownloadFeedback::where('video_download_id', $download->id)
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->where('status', VideoDownloadFeedbackStatus::PENDING->value)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($existsRecent) {
            return response()->json([
                'success' => false,
                'message' => '您近期已提交过该下载项的反馈，管理员正在核实处理中，请勿重复提交。',
            ], 429);
        }

        // 4. 写入反馈记录
        VideoDownloadFeedback::create([
            'video_id' => $video->id,
            'video_download_id' => $download->id,
            'user_id' => $userId,
            'type' => $validated['type'],
            'content' => $validated['content'] ? trim($validated['content']) : null,
            'status' => VideoDownloadFeedbackStatus::PENDING->value,
        ]);

        return response()->json([
            'success' => true,
            'message' => '纠错反馈提交成功！感谢您的指正与贡献，我们会尽快核查处理。',
        ], 200);
    }
}

