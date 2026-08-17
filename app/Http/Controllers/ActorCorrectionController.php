<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use App\Models\ActorCorrection;
use Illuminate\Http\Request;

class ActorCorrectionController extends Controller
{
    /**
     * 保存用户提交的演员修正申请
     */
    public function store(Request $request, Actor $actor)
    {
        $validated = $request->validate([
            'basic_info' => 'nullable|array',
            'physical_info' => 'nullable|array',
            'socials' => 'nullable|array',
            'custom_fields' => 'nullable|array',
        ]);

        // 将自定义属性合并到对应的类别中
        $basicInfo = $validated['basic_info'] ?? [];
        $physicalInfo = $validated['physical_info'] ?? [];
        $socials = $validated['socials'] ?? [];

        if (!empty($validated['custom_fields'])) {
            foreach ($validated['custom_fields'] as $field) {
                if (!empty($field['key']) && !empty($field['value'])) {
                    $category = $field['category'] ?? 'basic_info';
                    if ($category === 'basic_info') {
                        $basicInfo[$field['key']] = $field['value'];
                    } elseif ($category === 'physical_info') {
                        $physicalInfo[$field['key']] = $field['value'];
                    } elseif ($category === 'socials') {
                        $socials[$field['key']] = $field['value'];
                    }
                }
            }
        }

        // 保存修正申请记录
        ActorCorrection::create([
            'actor_id' => $actor->id,
            'user_id' => $request->user()?->id,
            'payload' => [
                'basic_info' => $basicInfo,
                'physical_info' => $physicalInfo,
                'socials' => $socials,
            ],
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => '修正建议提交成功，感谢您的贡献！'
        ], 200);
    }
}
