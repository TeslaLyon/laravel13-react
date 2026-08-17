<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class ActorDetail extends Model
{
    protected $fillable = [
        'actor_id',
        'basic_info',
        'physical_info',
        'socials',
        'gallery',
    ];

    protected function casts(): array
    {
        return [
            'basic_info' => 'array',
            'physical_info' => 'array',
            'socials' => 'array',
        ];
    }

    // 定义反向关系：属于哪个演员
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }
}
