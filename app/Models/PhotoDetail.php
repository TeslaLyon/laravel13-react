<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoDetail extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'gallery'       => 'array',
            'download_info' => 'array',
            'extra_meta'    => 'array',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}

