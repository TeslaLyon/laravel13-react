<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPrivacyPolicy
{
    /**
     * 判定是否允许查看视频收藏
     */
    public function viewVideoFavorites(?User $viewer, User $author): bool
    {
        return $this->checkAccess($viewer, $author, 'video_favorites');
    }

    /**
     * 判定是否允许查看演员收藏
     */
    public function viewActorFavorites(?User $viewer, User $author): bool
    {
        return $this->checkAccess($viewer, $author, 'actor_favorites');
    }

    /**
     * 判定是否允许查看点赞的视频
     */
    public function viewLikedVideos(?User $viewer, User $author): bool
    {
        return $this->checkAccess($viewer, $author, 'liked_videos');
    }

    /**
     * 核心权限判定引擎
     */
    protected function checkAccess(?User $viewer, User $author, string $settingKey): bool
    {
        // 1. 本人访问自己的空间：无条件允许
        if ($viewer && $viewer->id === $author->id) {
            return true;
        }

        // 2. 提取目标用户的隐私配置等级
        $accessLevel = $author->getPrivacySetting($settingKey, 'public');

        // 仅自己可见
        if ($accessLevel === 'private') {
            return false;
        }

        // 公开访问
        if ($accessLevel === 'public') {
            return true;
        }

        // 仅关注者（粉丝）可见
        if ($accessLevel === 'followers') {
            if (!$viewer) {
                return false; // 游客未登录不可见
            }

            // 利用系统现有的关注关系判定（当前访问者是否是作者的粉丝）
            return method_exists($author, 'isSubscribedBy')
                ? $author->isSubscribedBy($viewer)
                : false;
        }

        return false;
    }
}
