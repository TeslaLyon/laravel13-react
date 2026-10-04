<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Cog\Contracts\Love\Reactable\Models\Reactable as ReactableInterface;
use Cog\Laravel\Love\Reactable\Models\Traits\Reactable;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Actor extends Model implements ReactableInterface
{
    use Reactable, Searchable;

    protected $fillable = [
        'name',
        'slug',
        'avatar',
        'booty_img',
        'banner',
        'gender',
        'is_trans_model',
        'original_id',
    ];

    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'gender' => $this->gender,
        ];
    }

    protected $casts = [
        'booty_img' => 'array',
    ];

    public function detail(): HasOne
    {
        return $this->hasOne(ActorDetail::class);
    }

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'actor_photo');
    }
}
