<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Node extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'node_type',
        'title',
        'slug',
        'description',
        'icon',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * 关联：父节点
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'parent_id');
    }

    /**
     * 关联：子节点列表
     */
    public function children(): HasMany
    {
        return $this->hasMany(Node::class, 'parent_id')->orderBy('display_order', 'asc');
    }

    /**
     * 关联：版块特有属性 (1:1 扩展表)
     */
    public function forum(): HasOne
    {
        return $this->hasOne(Forum::class, 'node_id');
    }

    /**
     * 关联：该节点下的主题帖
     */
    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class, 'node_id');
    }
}
