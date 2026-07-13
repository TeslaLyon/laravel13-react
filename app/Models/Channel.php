<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Cog\Contracts\Love\Reactable\Models\Reactable as ReactableInterface;
use Cog\Laravel\Love\Reactable\Models\Traits\Reactable;

/**
 * @property int $id
 * @property string $name 片商名称
 * @property string $slug 片商slug
 * @property int $data_crawl_type
 * @property int $video_num
 * @property int $follow_num
 * @property string $avatar 片商(小)头像
 * @property string $logo 片商(大)logo
 * @property string $official_website_url
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property string|null $deleted_at
 * @property int|null $love_reactant_id
 * @property-read \Cog\Laravel\Love\Reactant\Models\Reactant|null $loveReactant
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Video> $videos
 * @property-read int|null $videos_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereDataCrawlType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereFollowNum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereLoveReactantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereOfficialWebsiteUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Channel whereVideoNum($value)
 * @mixin \Eloquent
 */
class Channel extends Model implements ReactableInterface
{
    use Reactable;


    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }
}
