<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VideoDownloadSubmission;
use Illuminate\Support\Facades\Auth;

class VideoDownloadSubmissionController extends Controller
{
    // TODO:上传种子文件功能师傅哦保留还有待商榷
    public function store(Request $request)
    {
        $validated = $request->validate([
            'video_id' => 'required|exists:videos,id',
            'type' => 'required|in:torrent,magnet,link',
            'content' => 'required_unless:type,torrent|nullable|string',
            'torrent_file' => 'required_if:type,torrent|nullable|file|mimes:torrent|max:10240',
            'extraction_code' => 'nullable|string|max:50',   // 提取码
            'archive_password' => 'nullable|string|max:100',  // 解压密码
            'remark' => 'nullable|string|max:500',   // 备注说明
        ]);

        $content = $request->input('content');

        // 处理种子文件上传
        if ($request->input('type') === 'torrent' && $request->hasFile('torrent_file')) {
            $path = $request->file('torrent_file')->store('torrents', 'public');
            $content = $path;
        }

        VideoDownloadSubmission::create([
            'user_id' => Auth::id(),
            'video_id' => $validated['video_id'],
            'type' => $validated['type'],
            'content' => $content,
            'extraction_code' => $request->input('extraction_code'),
            'archive_password' => $request->input('archive_password'),
            'remark' => $request->input('remark'),
            'status' => 0, // 待审核
        ]);

        return response()->json([
            'success' => true,
            'message' => '提交成功，感谢您的贡献！'
        ], 200);
    }
}
