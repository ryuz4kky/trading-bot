<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotSetting extends Model
{
    protected $fillable = [
        'bot_id',
        'pairs',
        'risk_percent',
        'stop_loss_percent',
        'take_profit_percent',
        'ema_fast',
        'ema_slow',
        'rsi_period',
        'kline_limit',
        'kline_interval',
        'max_positions',
        'strategy',
        'bb_period',
        'max_daily_loss_percent',
        'trailing_sl_enabled',
        'trailing_sl_percent',
        'cooldown_candles',
        'volume_min_ratio',
    ];

    public const STRATEGIES = [
        'ema_crossover'      => 'EMA Crossover + RSI (Trend Following)',
        'rsi_mean_reversion' => 'RSI Mean Reversion + Bollinger Bands',
        'bb_squeeze'         => 'Bollinger Bands Squeeze',
    ];

    protected $casts = [
        'pairs'               => 'array',
        'risk_percent'            => 'decimal:2',
        'stop_loss_percent'       => 'decimal:2',
        'take_profit_percent'     => 'decimal:2',
        'max_daily_loss_percent'  => 'decimal:2',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
