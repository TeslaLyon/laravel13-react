<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DelayedProcessingRepeatActor extends Model
{
    protected $fillable = [
        'channel_id',
        'video_slug',
        'actor_slug',
        'type',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'type'   => 'integer',
            'status' => 'integer',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}

