<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ForumCategory extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 明确指定数据库表名
     */
    protected $table = 'forum_categories';

    protected $fillable = [
        'parent_id',
        'name',
        'name_zh',
        'slug',
        'description',
        'icon',
        'threads_count',
        'posts_count',
        'sort',
    ];

    /**
     * 关联：父级分类/版块
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'parent_id');
    }

    /**
     * 关联：包含的子版块列表
     */
    public function children(): HasMany
    {
        return $this->hasMany(ForumCategory::class, 'parent_id')->orderBy('sort', 'asc');
    }

    /**
     * 关联：该版块下的所有主题帖
     */
    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class, 'forum_category_id');
    }

    /**
     * 关联：最新发布的一条主题 (用于 XenForo 风格右侧最新动态展示)
     */
    public function latestThread(): HasOne
    {
        return $this->hasOne(Thread::class, 'forum_category_id')->latestOfMany();
    }
}
