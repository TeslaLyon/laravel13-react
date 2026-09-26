<?php

namespace App\Models;

use Cog\Contracts\Love\Reactable\Models\Reactable as ReactableInterface;
use Cog\Laravel\Love\Reactable\Models\Traits\Reactable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Photo extends Model implements ReactableInterface
{
    use Reactable;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cover_img'          => 'array',
            'is_trans_model'     => 'boolean',
            'sexual_orientation' => 'integer',
            'status'             => 'integer',
            'total'              => 'integer',
            'release_at'         => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function photoDetail(): HasOne
    {
        return $this->hasOne(PhotoDetail::class);
    }

    public function actors(): BelongsToMany
    {
        return $this->belongsToMany(Actor::class, 'actor_photo');
    }
}

