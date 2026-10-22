<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserAvatarService
{
    public function __construct(
        protected CreditService $creditService
    ) {}

    /**
     * 获取可供选择的默认头像列表 (用于后台 Select 下拉选项)
     *
     * @return array<string, string>
     */
    public function getDefaultAvatarSelectOptions(): array
    {
        $defaults = config('avatars.defaults', []);
        $options = [];

        foreach ($defaults as $item) {
            $options[$item['url']] = $item['name'];
        }

        // 增加清空选项
        $options['__REMOVE__'] = '移除头像（重置为空，使用首字母文字头像）';

        return $options;
    }

    /**
     * 下架违规头像核心业务逻辑
     *
     * @param  User  $user  目标用户
     * @param  string|null  $replacementAvatarUrl  替换的目标头像 URL（如为 '__REMOVE__' 或 null 则清空）
     * @param  bool  $recordViolation  是否计入违规并根据规则扣除积分
     * @param  int  $violationAmount  增加违规次数（默认 1 次）
     * @param  string  $reason  违规原因/备注
     * @param  bool  $deleteOldFile  是否清理本地存储文件
     * @param  int|null  $operatorId  操作管理员 ID
     * @return array<string, mixed>
     */
    public function takeDownAvatar(
        User $user,
        ?string $replacementAvatarUrl = null,
        bool $recordViolation = true,
        int $violationAmount = 1,
        string $reason = '头像包含违规内容，已下架处理',
        bool $deleteOldFile = true,
        ?int $operatorId = null
    ): array {
        $oldAvatar = $user->avatar;

        // 1. 如果旧头像是上传在本地 public 存储中的文件且需要清理
        if ($deleteOldFile && !empty($oldAvatar) && Str::startsWith($oldAvatar, '/storage/')) {
            try {
                $filePath = Str::replaceFirst('/storage/', '', $oldAvatar);
                if (Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                }
            } catch (\Throwable $e) {
                Log::warning("下架头像时清理旧文件失败 [user_id: {$user->id}]: " . $e->getMessage());
            }
        }

        // 2. 设置新头像
        if ($replacementAvatarUrl === '__REMOVE__' || empty($replacementAvatarUrl)) {
            $user->avatar = null;
        } else {
            $user->avatar = $replacementAvatarUrl;
        }

        $user->save();

        // 3. 选择性记录违规并扣除积分
        $creditResult = null;
        if ($recordViolation && $violationAmount > 0) {
            $creditResult = $this->creditService->incrementMetric(
                user: $user,
                metricColumn: 'violation_count',
                amount: $violationAmount,
                action: 'avatar_violation',
                remark: $reason,
                source: null,
                operatorId: $operatorId
            );
        }

        return [
            'success' => true,
            'old_avatar' => $oldAvatar,
            'new_avatar' => $user->avatar,
            'violation_recorded' => $recordViolation,
            'violation_count' => $user->violation_count,
            'total_credits' => $user->total_credits,
            'credit_result' => $creditResult,
        ];
    }
}

