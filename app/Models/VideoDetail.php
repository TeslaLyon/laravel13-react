<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class videoDetail extends Model
{
    protected function casts(): array
    {
        return [
            'download_info' => 'array',
            // 'screen_img' => 'json',
            'list_img_large_meta' => 'array',
        ];
    }
}
