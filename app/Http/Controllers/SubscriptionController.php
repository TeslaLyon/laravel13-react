<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Models\Channel;
use Cog\Contracts\Love\Reactable\Models\Reactable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Sleep;

class SubscriptionController extends Controller
{
    protected array $modelsMap = [
        'channel' => Channel::class,
        'user' => User::class,
        'actor' => Actor::class,
        'category' => Category::class,
        'tag' => Tag::class,
    ];

    protected function resolveTarget(string $type, int $id): Reactable
    {
        if (!isset($this->modelsMap[$type])) {
            abort(404, '不支持的订阅目标类型');
        }

        $modelClass = $this->modelsMap[$type];
        return $modelClass::findOrFail($id);
    }

    /**
     * 订阅实体 / 切换通知偏好
     */
    public function subscribe(Request $request, string $type, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $target = $this->resolveTarget($type, $id);

        if ($target instanceof User && $user->id === $target->id) {
            return response()->json(['message' => '无法订阅自己的频道'], 422);
        }

        $validated = $request->validate([
            'notification_type' => ['nullable', 'string', Rule::in(['all', 'personalized', 'none'])],
        ]);

        $notificationType = $validated['notification_type'] ?? 'personalized';

        // 🎯 调用规范命名的方法
        $user->subscribeToEntity($target, $notificationType);
        Sleep::for(2000)->milliseconds();
        return response()->json([
            'message' => '订阅成功',
            'is_following' => true,
            'notification_type' => $notificationType,
            'followers_count' => $user->getEntitySubscribersCount($target),
        ]);
    }

    /**
     * 取消订阅实体
     */
    public function unsubscribe(Request $request, string $type, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $target = $this->resolveTarget($type, $id);

        $user->unsubscribeFromEntity($target);
        Sleep::for(2000)->milliseconds();
        return response()->json([
            'message' => '已取消订阅',
            'is_following' => false,
            'notification_type' => 'personalized',
            'followers_count' => $user->getEntitySubscribersCount($target),
        ]);
    }

    /**
     * 修改通知偏好
     */
    public function updateNotification(Request $request, string $type, int $id): JsonResponse
    {
        $target = $this->resolveTarget($type, $id);

        /** @var User $user */
        $user = $request->user();

        // 1. 参数校验：确保 notification_type 仅为 all, personalized, none
        $validated = $request->validate([
            'notification_type' => [
                'required',
                'string',
                Rule::in(['all', 'personalized', 'none']),
            ],
        ], [
            'notification_type.required' => '请选择通知类型',
            'notification_type.in' => '通知类型参数无效',
        ]);
        Sleep::for(2000)->milliseconds();
        // 2. 前置检查：必须处于已订阅状态才能修改偏好
        if (!$user->hasSubscribedToEntity($target)) {
            return response()->json([
                'message' => '您尚未关注，无法设置通知偏好',
            ], 422);
        }

        // 3. 执行变更：通过 SubscribesWithLove 自动替换为新的 Reaction
        $user->subscribeToEntity($target, $validated['notification_type']);

        // 4. 返回与前端 useHttp 契约一致的 JSON 响应
        return response()->json([
            'message' => '通知偏好已更新',
            'notification_type' => $validated['notification_type'],
            'is_following' => true,
            'followers_count' => $user->getEntitySubscribersCount($target),
        ]);
    }
}
