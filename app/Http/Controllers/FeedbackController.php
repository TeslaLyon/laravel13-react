<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller
{
    /**
     * 存储通用反馈
     */
    public function store(Request $request)
    {
        // 1. 验证传入的数据
        $validated = $request->validate([
            // 规定前端必须传入模块类型，这里配置你支持的所有模块别名
            'model_type' => ['required', 'string', 'in:video,picture,channel,category,store,forum'],
            'model_id' => ['required', 'integer'],
            // 反馈的具体内容
            'data.type' => ['required', 'string'],
            'data.content' => ['nullable', 'string', 'max:1000'],
        ]);

        // 2. 根据前端传来的类型，映射到真实的 Laravel 模型类
        $modelClass = match ($validated['model_type']) {
            'video' => \App\Models\Video::class,
            // 'picture' => \App\Models\Picture::class,
            'channel' => \App\Models\Channel::class,
            'category' => \App\Models\Category::class,
            'tag' => \App\Models\Tag::class,
            // 'store' => \App\Models\Store::class,
            // 'forum' => \App\Models\Forum::class,
        };
        // dd($request->all());

        // 3. 验证该数据是否真的存在于数据库中
        $targetModel = $modelClass::findOrFail($validated['model_id']);

        // 4. 使用多态关联创建反馈记录
        $targetModel->feedback()->create([
            'user_id' => Auth::id(),
            'type' => $validated['data']['type'],
            'content' => $validated['data']['content'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => '反馈提交成功'
        ], 200);
    }
}
