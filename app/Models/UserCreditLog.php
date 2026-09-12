<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserCreditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'operator_id',
        'action',
        'field',
        'change_amount',
        'after_value',
        'current_total_credits',
        'remark',
        'source_type',
        'source_id',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'change_amount' => 'decimal:2',
        'after_value' => 'decimal:2',
        'current_total_credits' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
