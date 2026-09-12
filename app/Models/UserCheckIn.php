<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCheckIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'check_in_date',
        'type',
        'continuous_days_snapshot',
        'total_days_snapshot',
        'reward_coins',
        'bonus_coins',
        'extra_rewards'
    ];

    protected $casts = [
        'check_in_date' => 'date:Y-m-d',
        'continuous_days_snapshot' => 'integer',
        'total_days_snapshot' => 'integer',
        'reward_coins' => 'integer',
        'bonus_coins' => 'integer',
        'extra_rewards' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
