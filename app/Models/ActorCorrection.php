<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Enums\ActorCorrectionStatus;


class ActorCorrection extends Model
{
    protected $fillable = [
        'actor_id',
        'user_id',
        'payload',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ActorCorrectionStatus::class,
            'payload' => 'array',
        ];
    }
}
