<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'slug',
        'title',
        'excerpt',
        'cover_image',
        'read_time',
        'views_count',
        'status',
        'published_at',
    ];

    /**
     * 关联：作者 (BelongsTo)
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联：详情正文 (HasOne)
     */
    public function detail(): HasOne
    {
        return $this->hasOne(ArticleDetail::class, 'article_id');
    }

    /**
     * 关联：多分类 (BelongsToMany)
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'article_category');
    }

    /**
     * 关联：多标签 (BelongsToMany)
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'article_tag');
    }

    /**
     * 本地作用域：仅查询已发布的文章
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopePublished($query)
    {
        return $query->where('status', 1);
    }
}
