<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AvatarDecoration extends Model
{
    protected $fillable = [
        'title',
        'code',
        'image_url',
        'description',
        'criteria',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'criteria' => 'array',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_avatar_decorations')
            ->withPivot(['unlocked_at', 'expires_at'])
            ->withTimestamps();
    }
}
