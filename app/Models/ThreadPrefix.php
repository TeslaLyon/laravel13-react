<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThreadPrefix extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'thread_prefixes';

    protected $fillable = [
        'name',
        'slug',
        'bg_color',
        'text_color',
        'description',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * 关联使用此前缀的所有主题帖
     */
    public function threads(): BelongsToMany
    {
        return $this->belongsToMany(Thread::class, 'thread_prefix_thread', 'prefix_id', 'thread_id')
            ->withTimestamps();
    }
}
