<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Cog\Contracts\Love\Reactable\Models\Reactable as ReactableInterface;
use Cog\Laravel\Love\Reactable\Models\Traits\Reactable;

class Tag extends Model implements ReactableInterface
{
    use Reactable;
    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class);
    }

    /**
     * 关联：拥有该标签的所有文章 (多对多)
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
