<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPrivacySetting extends Model
{
    protected $fillable = [
        'user_id',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 安全读取特定项的权限，内置全站兜底
     */
    public function getSetting(string $key, string $default = 'public'): string
    {
        return $this->settings[$key] ?? $default;
    }
}
