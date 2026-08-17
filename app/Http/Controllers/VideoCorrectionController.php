<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VideoCorrection;
use App\Models\Video;
use Illuminate\Support\Sleep;

class VideoCorrectionController extends Controller
{
    // TODO：审核通过后增加奖励
    public function store(Video $video, Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:actors,categories,tags,name_zh,description'],
            'payload' => ['required'], // 可能是数组 (如 [1, 2])，也可能是字符串
        ]);

        // 如果 payload 是数组（关联类型），编码为 JSON 字符串保存；否则直接保存文本
        $payloadValue = is_array($validated['payload'])
            ? json_encode($validated['payload'])
            : trim((string) $validated['payload']);

        VideoCorrection::create([
            'user_id' => $request->user()->id,
            'video_id' => $video->id,
            'type' => $validated['type'],
            'payload' => $payloadValue,
            'status' => 'pending',
        ]);

        Sleep::for(1000)->milliseconds();

        return response()->json([
            'success' => true,
            'message' => '修正建议提交成功，感谢您的贡献！'
        ], 200);
    }
}
