<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Models\BotSetting;
use Illuminate\Console\Command;

class ApplyOptimalSettings extends Command
{
    protected $signature   = 'bot:apply-optimal';
    protected $description = 'Terapkan settings optimal: EMA9/21, 1H, SL4%, TP11%, Trailing SL, 2 posisi';

    public function handle(): int
    {
        $bot = Bot::first();

        if (! $bot) {
            $this->error('Bot tidak ditemukan.');
            return 1;
        }

        BotSetting::updateOrCreate(
            ['bot_id' => $bot->id],
            [
                'strategy'              => 'ema_crossover',
                'pairs'                 => ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT', 'XRPUSDT'],
                'ema_fast'              => 9,
                'ema_slow'              => 21,
                'rsi_period'            => 14,
                'bb_period'             => 20,
                'kline_interval'        => '1h',
                'stop_loss_percent'     => 4.0,
                'take_profit_percent'   => 11.0,
                'rsi_buy_threshold'     => 45,
                'adx_trend_threshold'   => 20,
                'volume_min_ratio'      => 1.5,
                'max_positions'         => 2,
                'trailing_sl_enabled'   => true,
                'trailing_sl_percent'   => 2.5,
                'cooldown_candles'      => 2,
                'max_daily_loss_percent' => 6.0,
                'risk_percent'          => 2.0,
            ]
        );

        $this->info('Settings optimal berhasil diterapkan:');
        $this->table(
            ['Parameter', 'Nilai'],
            [
                ['Strategi',          'EMA Crossover (EMA9/21)'],
                ['Timeframe',         '1 Jam (1H)'],
                ['Pairs',             'BTC, ETH, SOL, BNB, XRP'],
                ['Stop Loss',         '4%'],
                ['Take Profit',       '11% (R:R ~1:2.75)'],
                ['RSI Buy Range',     '45 – 65'],
                ['ADX Min',           '20'],
                ['Volume Min Ratio',  '1.5x'],
                ['Max Posisi',        '2'],
                ['Trailing SL',       'ON — 2.5%'],
                ['Cooldown Candles',  '2 candle (2 jam)'],
                ['Max Daily Loss',    '6%'],
            ]
        );

        return 0;
    }
}
