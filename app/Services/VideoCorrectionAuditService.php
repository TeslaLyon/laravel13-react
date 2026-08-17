<?php

namespace App\Services;

use App\Models\RewardLog;
use App\Models\User;
use App\Models\VideoCorrection;
use App\Models\VideoHistory;
use Illuminate\Support\Facades\DB;

class VideoCorrectionAuditService
{
    public function approve(VideoCorrection $correction, User $adminUser, int $contribReward = 10, int $pointsReward = 50): void
    {
        if ($correction->status !== 'pending') {
            return;
        }

        DB::transaction(function () use ($correction, $adminUser, $contribReward, $pointsReward) {
            $video = $correction->video;
            $data = $correction->formatted_payload; // 获取动态解析后的数据

            // 1. 分流更新视频数据
            if (in_array($correction->type, ['actors', 'categories', 'tags'])) {
                // 处理多对多关联中间表同步
                $video->{$correction->type}()->sync($data);
            } elseif ($correction->type === 'title_cn') {
                // 处理视频直接属性更新
                $video->update(['title_cn' => $data]);
            } elseif ($correction->type === 'description') {
                $video->update(['description' => $data]);
            }

            // 2. 记录变更历史 (VideoHistory)
            // VideoHistory::create([
            //     'video_id' => $video->id,
            //     'operator_id' => $adminUser->id,
            //     'correction_id' => $correction->id,
            //     'action_type' => "update_{$correction->type}",
            //     'new_values' => [$correction->type => $data],
            //     'remark' => "采纳用户 [ID: {$correction->user_id}] 对 {$correction->type} 的修正建议",
            // ]);

            // // 3. 更新提案状态
            // $correction->update([
            //     'status' => 'approved',
            //     'reviewer_id' => $adminUser->id,
            //     'reviewed_at' => now(),
            // ]);

            // // 4. 发放贡献值和积分奖励
            // $contributor = $correction->user;
            // $contributor->increment('contribution_points', $contribReward);
            // $contributor->increment('reward_points', $pointsReward);

            // // 5. 记录奖励日志
            // RewardLog::create([
            //     'user_id' => $contributor->id,
            //     'source_type' => VideoCorrection::class,
            //     'source_id' => $correction->id,
            //     'contribution_points' => $contribReward,
            //     'reward_points' => $pointsReward,
            //     'description' => "视频《{$video->name}》资料修正建议被采纳奖励",
            // ]);
        });
    }
}
