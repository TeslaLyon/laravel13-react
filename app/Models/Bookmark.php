<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'custom_name', 'bookmarkable_id', 'bookmarkable_type'])]
class Bookmark extends Model
{

    public function bookmarkable(): MorphTo
    {
        return $this->morphTo();
    }
}
