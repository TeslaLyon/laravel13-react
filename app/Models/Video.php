<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Cog\Contracts\Love\Reactable\Models\Reactable as ReactableInterface;
use Cog\Laravel\Love\Reactable\Models\Traits\Reactable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name 视频原始标题
 * @property string $name_zh 视频中文标题
 * @property string $slug 视频slug
 * @property string $source_uuid 视频源uuid
 * @property string|null $list_img 列表图片
 * @property string|null $preview 视频预览:1-视频;2-动态图;3-多张图片
 * @property string|null $release_at 视频发布时间
 * @property string $video_code 视频编码
 * @property int|null $channel_id 片商 id
 * @property bool $is_4k 是否为 4k 画质
 * @property bool $is_vr 是否为 VR
 * @property int $sexual_orientation 性取向：[0-未知;1-异性;2-女同性恋]
 * @property bool $is_trans_model 演员是否为变性人
 * @property string $max_quality 最高画质，例：2160p,1080p
 * @property int $status 状态：[0-下架;1-上架]
 * @property int $likes_count
 * @property int $favorites_count
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property string|null $deleted_at
 * @property-read \App\Models\Channel|null $channel
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereChannelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereFavoritesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereIs4k($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereIsTransModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereIsVr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereLikesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereListImg($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereMaxQuality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereNameZh($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video wherePreview($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereReleaseAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereSexualOrientation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereSourceUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereVideoCode($value)
 * @mixin \Eloquent
 */
class Video extends Model implements ReactableInterface
{
    use Reactable;

    protected function casts(): array
    {
        return [
            'list_img' => 'array',
        ];
    }

    public function channel(): BelongsTo
    {
        // 默认情况下，Laravel 会自动寻找 channel_id 作为外键
        return $this->belongsTo(Channel::class);
    }

    public function actors(): BelongsToMany
    {
        return $this->belongsToMany(Actor::class, 'actor_video');
    }

    public function videoDetail(): HasOne
    {
        return $this->hasOne(VideoDetail::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function bookmarks()
    {
        return $this->morphMany(Bookmark::class, 'bookmarkable');
    }

    public function feedback()
    {
        return $this->morphMany(Feedback::class, 'feedbackable');
    }

    /**
     * 获取该视频下的所有字幕记录（一对多）。
     * 包含 pending, approved, rejected 的所有状态。
     */
    public function subtitles(): HasMany
    {
        return $this->hasMany(VideoSubtitle::class);
    }

    /**
     * 获取该视频已审核通过的唯一/优先字幕（一对一）。
     * 结合我们之前设计的 status 字段进行过滤。
     */
    public function approvedSubtitles(): HasMany
    {
        return $this->hasMany(VideoSubtitle::class)
            ->where('status', 'approved')
            ->with('user:id,name,nickname');
    }
}
