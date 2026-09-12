<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserGroup extends Model
{
    use HasFactory;

    /**
     * 允许批量赋值的属性白名单
     */
    protected $fillable = [
        // 1. 基础标识与头衔
        'name',
        'title',
        'level',
        'is_credit_based',

        // 2. 积分与等级晋升阈值
        'credits_min',
        'credits_max',

        // 3. 样式与视觉展现
        'username_css',
        'display_style_priority',
        'banner_text',
        'banner_bg_color',
        'banner_text_color',
        'banner_icon',

        // 4. 系统与管理状态标识
        'is_system',
        'is_staff',
        'is_banned',

        // 5. 权限控制开关
        'read_permission_level',
        'allow_search',
        'allow_custom_title',
        'allow_upload_attachment',
        'daily_post_limit',
        'extra_permissions',
    ];

    /**
     * 原生类型转换配置
     */
    protected $casts = [
        // 等级与积分
        'level' => 'integer',
        'is_credit_based' => 'boolean',
        'credits_min' => 'decimal:2',
        'credits_max' => 'decimal:2',

        // 视觉与系统状态
        'display_style_priority' => 'integer',
        'is_system' => 'boolean',
        'is_staff' => 'boolean',
        'is_banned' => 'boolean',

        // 权限属性
        'read_permission_level' => 'integer',
        'allow_search' => 'boolean',
        'allow_custom_title' => 'boolean',
        'allow_upload_attachment' => 'boolean',
        'daily_post_limit' => 'integer',
        'extra_permissions' => 'array',
    ];

    // ==================== 关联关系 ====================

    /**
     * 以该组作为主用户组的所有用户
     */
    public function primaryUsers(): HasMany
    {
        return $this->hasMany(User::class, 'primary_group_id');
    }

    /**
     * 关联别名（兼顾部分业务场景下以 users 调用的习惯）
     */
    public function users(): HasMany
    {
        return $this->primaryUsers();
    }

    /**
     * 以该组作为附属/次要用户组的用户集合
     */
    public function secondaryUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_group_user')
            ->withPivot('is_displayed')
            ->withTimestamps();
    }

    // ==================== 查询作用域 (Scopes) ====================

    /**
     * 仅查询基于积分自动晋升的用户组
     */
    public function scopeCreditBased(Builder $query): Builder
    {
        return $query->where('is_credit_based', true);
    }

    /**
     * 仅查询系统特殊组（如管理组、封禁组等）
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    /**
     * 按等级从低到高排序
     */
    public function scopeOrderedByLevel(Builder $query): Builder
    {
        return $query->orderBy('level', 'asc');
    }

    // ==================== 辅助方法 ====================

    /**
     * 判定指定积分是否落在该等级区间内
     */
    public function containsCredits(float $credits): bool
    {
        if ($credits < (float) $this->credits_min) {
            return false;
        }

        if ($this->credits_max !== null && $credits > (float) $this->credits_max) {
            return false;
        }

        return true;
    }
}
