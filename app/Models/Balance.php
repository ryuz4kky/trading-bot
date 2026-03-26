<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Balance extends Model
{
    protected $fillable = [
        'bot_id',
        'mode',
        'currency',
        'amount',
        'locked',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'locked' => 'decimal:8',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function getAvailableAttribute(): float
    {
        return (float) $this->amount - (float) $this->locked;
    }
}
