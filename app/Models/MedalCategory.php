<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedalCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    // ==================== 关联关系 ====================

    /**
     * 分类下的所有勋章
     */
    public function medals(): HasMany
    {
        return $this->hasMany(Medal::class, 'category_id')->orderBy('display_order');
    }

    /**
     * 分类下处于上架状态的勋章
     */
    public function activeMedals(): HasMany
    {
        return $this->medals()->where('is_active', true);
    }

    // ==================== 查询作用域 ====================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('id');
    }
}
