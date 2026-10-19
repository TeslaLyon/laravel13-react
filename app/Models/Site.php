<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /**
     * 所属片商 (Channel)
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * 该站点/系列下包含的视频
     */
    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'site_video');
    }
}

