<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Bookmark extends Model
{

    protected $fillable = [
        'user_id',
        'custom_name',
        'bookmarkable_id',
        'bookmarkable_type'
    ];
    
    public function bookmarkable(): MorphTo
    {
        return $this->morphTo();
    }
}
