<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Cog\Contracts\Love\Reactable\Models\Reactable as ReactableInterface;
use Cog\Laravel\Love\Reactable\Models\Traits\Reactable;
use App\Concerns\SubscribesWithLove;

// TODO: 待添加 Reactable
class Category extends Model implements ReactableInterface
{
    use Reactable, SubscribesWithLove;

    protected $guarded = [];

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class);
    }

    /**
     * 关联：属于该分类的所有文章 (多对多)
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }
}
