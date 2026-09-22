<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoDetail extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'download_info'       => 'array',
            'screen_img'          => 'array',
            'list_img_large_meta' => 'array',
        ];
    }
}
