<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trade extends Model
{
    protected $fillable = [
        'bot_id',
        'pair',
        'binance_pair',
        'type',
        'mode',
        'status',
        'entry_price',
        'exit_price',
        'quantity',
        'amount_idr',
        'stop_loss_price',
        'take_profit_price',
        'profit_loss',
        'profit_loss_percent',
        'signal',
        'close_reason',
        'indicators',
        'exchange_order_id',
        'closed_at',
    ];

    protected $casts = [
        'entry_price'          => 'decimal:8',
        'exit_price'           => 'decimal:8',
        'quantity'             => 'decimal:8',
        'amount_idr'           => 'decimal:2',
        'stop_loss_price'      => 'decimal:8',
        'take_profit_price'    => 'decimal:8',
        'profit_loss'          => 'decimal:2',
        'profit_loss_percent'  => 'decimal:4',
        'indicators'           => 'array',
        'closed_at'            => 'datetime',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function isProfitable(): bool
    {
        return ($this->profit_loss ?? 0) > 0;
    }

    public function getDurationAttribute(): ?string
    {
        if (! $this->closed_at) {
            return null;
        }

        return $this->created_at->diffForHumans($this->closed_at, true);
    }
}
