<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medal extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'code',
        'title',
        'description',
        'condition_text',
        'icon_url',
        'rarity',
        'badge_color',
        'trophy_points',
        'award_type',
        'criteria',
        'is_hidden',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'criteria' => 'array',          // 自动序列化/反序列化 JSON 规则
        'trophy_points' => 'integer',
        'display_order' => 'integer',
        'is_hidden' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ==================== 关联关系 ====================

    /**
     * 所属分类
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MedalCategory::class, 'category_id');
    }

    /**
     * 拥有的用户关联记录
     */
    public function userMedals(): HasMany
    {
        return $this->hasMany(UserMedal::class, 'medal_id');
    }

    /**
     * 获得该勋章的用户集合
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_medals')
            ->using(UserMedal::class)
            ->withPivot(['id', 'awarded_by_user_id', 'award_reason', 'is_worn', 'wear_slot', 'unlocked_at'])
            ->withTimestamps();
    }

    // ==================== 查询作用域 ====================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_hidden', false);
    }

    public function scopeAutoAward(Builder $query): Builder
    {
        return $query->where('award_type', 'auto');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('id');
    }

    // ==================== 辅助方法 ====================

    /**
     * 检查某个用户是否已拥有该勋章
     */
    public function isUnlockedBy(int|User $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->userMedals()->where('user_id', $userId)->exists();
    }
}
