<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserTitleLadder extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'min_credits',
        'min_post_count',
        'text_color',
        'bg_color',
        'icon',
        'is_active',
    ];

    protected $casts = [
        'min_credits' => 'float',
        'min_post_count' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * 🎯 根据总积分寻找匹配的最高阶梯
     */
    public static function findLadderForCredits(float $credits, int $postCount = 0): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where('min_credits', '<=', $credits)
            ->where('min_post_count', '<=', $postCount)
            ->orderByDesc('min_credits')
            ->orderByDesc('min_post_count')
            ->first();
    }
}
