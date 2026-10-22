<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPrivacyPolicy
{
    /**
     * 判定是否允许查看个人主页信息（总闸门）
     */
    public function viewProfile(?User $viewer, User $author): bool
    {
        // 1. 本人访问自己的空间：无条件允许
        if ($viewer && $viewer->id === $author->id) {
            return true;
        }

        // 2. 提取目标用户的主页公开范围
        $accessLevel = $author->getPrivacySetting('profile_visibility', 'public');

        return match ($accessLevel) {
            'public' => true,
            'followers' => $author->isFollowedBy($viewer),
            'mutual' => $author->isMutualWith($viewer),
            'private' => false,
            default => true,
        };
    }

    /**
     * 判定是否允许查看视频收藏
     */
    public function viewVideoFavorites(?User $viewer, User $author): bool
    {
        if (!$this->viewProfile($viewer, $author)) {
            return false;
        }

        return $this->checkAccess($viewer, $author, 'video_favorites');
    }

    /**
     * 判定是否允许查看演员收藏
     */
    public function viewActorFavorites(?User $viewer, User $author): bool
    {
        if (!$this->viewProfile($viewer, $author)) {
            return false;
        }

        return $this->checkAccess($viewer, $author, 'actor_favorites');
    }

    /**
     * 判定是否允许查看点赞的视频
     */
    public function viewLikedVideos(?User $viewer, User $author): bool
    {
        if (!$this->viewProfile($viewer, $author)) {
            return false;
        }

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

        // 仅粉丝（关注者）可见
        if ($accessLevel === 'followers') {
            return $author->isFollowedBy($viewer);
        }

        // 互相关注好友可见
        if ($accessLevel === 'mutual') {
            return $author->isMutualWith($viewer);
        }

        return false;
    }
}
