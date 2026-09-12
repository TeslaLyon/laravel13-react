<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Video;
use App\Policies\UserPrivacyPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserSpaceTabService
{
    public function __construct(
        protected UserPrivacyPolicy $privacyPolicy
    ) {
    }

    /**
     * 读取每页限制条数（默认 1 条，最大上限 12 条）
     */
    protected function getPerPage(int $default = 1, int $max = 12): int
    {
        $perPage = (int) request()->query('per_page', $default);

        return ($perPage > 0 && $perPage <= $max) ? $perPage : $default;
    }

    /**
     * 解析视频收藏列表
     */
    public function resolveVideoCollections(?User $viewer, User $author): ?LengthAwarePaginator
    {
        if (!$this->privacyPolicy->viewVideoFavorites($viewer, $author)) {
            return null;
        }

        return Video::query()
            ->with('channel')
            ->whereReactedBy($author, 'VideoCollect')
            ->paginate($this->getPerPage())
            ->withQueryString();
    }

    /**
     * 解析视频点赞列表
     */
    public function resolveLikedVideos(?User $viewer, User $author): ?LengthAwarePaginator
    {
        if (!$this->privacyPolicy->viewLikedVideos($viewer, $author)) {
            return null;
        }

        return Video::query()
            ->with('channel')
            ->whereReactedBy($author, 'Like')
            ->paginate($this->getPerPage())
            ->withQueryString();
    }

    /**
     * 解析稍后观看列表（仅本人可见）
     */
    public function resolveWatchLaterVideos(?User $viewer, User $author): ?LengthAwarePaginator
    {
        if (!$viewer || $viewer->id !== $author->id) {
            return null;
        }

        return Video::query()
            ->select([
                'id',
                'name',
                'slug',
                'channel_id',
                'list_img',
                'preview',
                'release_at',
                'is_4k',
                'is_vr',
                'likes_count',
                'favorites_count',
                'created_at',
                'country'
            ])
            ->with('channel:id,name,slug,avatar,data_crawl_type')
            ->whereReactedBy($author, 'SaveToWatchLater')

            ->paginate($this->getPerPage())
            ->withQueryString();
    }

    /**
     * 解析图片收藏列表（预留）
     */
    public function resolveImageCollections(?User $viewer, User $author): ?LengthAwarePaginator
    {
        if (!$this->privacyPolicy->viewVideoFavorites($viewer, $author)) {
            return null;
        }

        return null;
    }

    /**
     * 聚合所有权限标记
     */
    public function resolvePermissions(?User $viewer, User $author): array
    {
        return [
            'canViewVideoCollections' => $this->privacyPolicy->viewVideoFavorites($viewer, $author),
            'canViewImageCollections' => $this->privacyPolicy->viewVideoFavorites($viewer, $author),
            'canViewLikedVideos' => $this->privacyPolicy->viewLikedVideos($viewer, $author),
            'canViewWatchLater' => $viewer && $viewer->id === $author->id,
        ];
    }
}
