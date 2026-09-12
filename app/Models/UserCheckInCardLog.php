<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserCheckInCardLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'change_count',
        'balance_before',
        'balance_after',
        'action',
        'description',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'change_count' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
