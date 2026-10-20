<?php

namespace App\Services;

use App\Enums\VideoCorrectionStatus;
use App\Models\Actor;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Models\VideoCorrection;
use App\Models\VideoHistory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VideoCorrectionAuditService
{
    /**
     * 审核通过并执行视频资料修改，同时生成不可篡改的变更审计记录
     *
     * @param VideoCorrection $correction 修正建议模型
     * @param User $adminUser 审核管理员
     * @param string|null $adminRemark 审核批注/说明
     * @param int $contribReward 奖励给用户的贡献值点数
     * @return VideoHistory
     */
    public function approve(
        VideoCorrection $correction,
        User $adminUser,
        ?string $adminRemark = null,
        int $contribReward = 10
    ): VideoHistory {
        if ($correction->status !== VideoCorrectionStatus::PENDING) {
            throw new InvalidArgumentException("该修正记录当前状态为 [{$correction->status->label()}]，不可重复审核。");
        }

        return DB::transaction(function () use ($correction, $adminUser, $adminRemark, $contribReward) {
            $video = $correction->video;
            if (!$video) {
                throw new InvalidArgumentException("关联的视频记录不存在或已被删除。");
            }

            $type = $correction->type;
            $oldValues = [];
            $newValues = [];

            // 1. 抓取原值快照并分流执行数据更新
            if ($type === 'actors') {
                $oldValues = $video->actors()->pluck('actors.name', 'actors.id')->toArray();
                $newIds = (array) $correction->formatted_payload;
                $video->actors()->sync($newIds);
                $newValues = Actor::whereIn('id', $newIds)->pluck('name', 'id')->toArray();
            } elseif ($type === 'categories') {
                $oldValues = $video->categories()->pluck('categories.name', 'categories.id')->toArray();
                $newIds = (array) $correction->formatted_payload;
                $video->categories()->sync($newIds);
                $newValues = Category::whereIn('id', $newIds)->pluck('name', 'id')->toArray();
            } elseif ($type === 'tags') {
                $oldValues = $video->tags()->pluck('tags.name', 'tags.id')->toArray();
                $newIds = (array) $correction->formatted_payload;
                $video->tags()->sync($newIds);
                $newValues = Tag::whereIn('id', $newIds)->pluck('name', 'id')->toArray();
            } elseif (in_array($type, ['name_zh', 'title_cn'], true)) {
                $oldValues = ['name_zh' => $video->name_zh];
                $newText = trim((string) $correction->payload);
                $video->update(['name_zh' => $newText]);
                $newValues = ['name_zh' => $newText];
            } elseif ($type === 'description') {
                $detail = $video->detail;
                $oldValues = ['description' => $detail?->description ?? ''];
                $newText = trim((string) $correction->payload);
                $video->detail()->updateOrCreate(['video_id' => $video->id], ['description' => $newText]);
                $newValues = ['description' => $newText];
            } else {
                throw new InvalidArgumentException("不支持的修正维度: {$type}");
            }

            // 2. 写入视频变更审计记录 (VideoHistory)
            $operatorName = $adminUser->nickname ?: $adminUser->name;
            $userName = $correction->user?->nickname ?: ($correction->user?->name ?: "用户#{$correction->user_id}");
            $typeLabel = $this->getTypeLabel($type);

            $defaultRemark = "管理员 [{$operatorName}] 采纳 {$userName} 的修正建议，更新了【{$typeLabel}】";
            $remark = !empty($adminRemark) ? "{$defaultRemark}。批注: {$adminRemark}" : $defaultRemark;

            $history = VideoHistory::create([
                'video_id'      => $video->id,
                'correction_id' => $correction->id,
                'operator_id'   => $adminUser->id,
                'action_type'   => "update_{$type}",
                'old_values'    => $oldValues,
                'new_values'    => $newValues,
                'remark'        => $remark,
            ]);

            // 3. 更新修正记录状态为“已通过”
            $correction->update([
                'status'      => VideoCorrectionStatus::APPROVED,
                'reviewer_id' => $adminUser->id,
                'reviewed_at' => now(),
            ]);

            // 4. 发放贡献值奖励给提交建议的用户
            if ($contribReward > 0 && $correction->user) {
                $contributor = $correction->user;
                $contributor->increment('contribution_points', $contribReward);
            }

            return $history;
        });
    }

    /**
     * 驳回用户的修正建议
     */
    public function reject(VideoCorrection $correction, User $adminUser, string $reason): void
    {
        if ($correction->status !== VideoCorrectionStatus::PENDING) {
            throw new InvalidArgumentException("该修正记录当前状态为 [{$correction->status->label()}]，不可重复审核。");
        }

        $correction->update([
            'status'        => VideoCorrectionStatus::REJECTED,
            'reviewer_id'   => $adminUser->id,
            'reject_reason' => trim($reason),
            'reviewed_at'   => now(),
        ]);
    }

    /**
     * 获取修正项类型的人类可读中文标签
     */
    public function getTypeLabel(string $type): string
    {
        return match ($type) {
            'name_zh', 'title_cn' => '中文标题',
            'description'         => '视频简介',
            'actors'              => '参演演员',
            'categories'          => '视频分类',
            'tags'                => '标签',
            default               => $type,
        };
    }
}
