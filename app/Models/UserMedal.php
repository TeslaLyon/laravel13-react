<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot; // 核心：导入 Pivot 基类

class UserMedal extends Pivot
{
    use HasFactory;

    protected $table = 'user_medals';

    /**
     * 明确声明支持自增主键
     * (Pivot 模型默认 $incrementing 为 false，因迁移中使用了 $table->id()，此处需设为 true)
     */
    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'medal_id',
        'awarded_by_user_id',
        'award_reason',
        'is_worn',
        'wear_slot',
        'unlocked_at',
    ];

    protected $casts = [
        'is_worn' => 'boolean',
        'wear_slot' => 'integer',
        'unlocked_at' => 'datetime',
    ];

    // ==================== 关联关系 ====================

    /**
     * 获得勋章的用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 勋章实体
     */
    public function medal(): BelongsTo
    {
        return $this->belongsTo(Medal::class, 'medal_id');
    }

    /**
     * 颁发人（若为人工颁发）
     */
    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by_user_id');
    }

    // ==================== 查询作用域 ====================

    public function scopeWorn(Builder $query): Builder
    {
        return $query->where('is_worn', true)->orderBy('wear_slot');
    }
}
