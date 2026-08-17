<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;


class Feedback extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'content',
        'status',
    ];

    /**
     * 获取拥有此反馈的父模型 (如 Video, Image, Forum 等)。
     */
    public function feedbackable()
    {
        return $this->morphTo();
    }
}
