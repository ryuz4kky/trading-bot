<?php

namespace App\Services;

class IndicatorService
{
    /**
     * Calculate Exponential Moving Average.
     * Uses SMA as seed, then applies Wilder's EMA formula.
     */
    public function calculateEMA(array $prices, int $period): float
    {
        $count = count($prices);

        if ($count < $period) {
            return 0.0;
        }

        $k = 2 / ($period + 1);

        // Seed: SMA of first $period prices
        $sma = array_sum(array_slice($prices, 0, $period)) / $period;
        $ema = $sma;

        // Apply EMA formula for remaining prices
        for ($i = $period; $i < $count; $i++) {
            $ema = ($prices[$i] * $k) + ($ema * (1 - $k));
        }

        return round($ema, 8);
    }

    /**
     * Calculate Relative Strength Index using Wilder's smoothing method.
     */
    public function calculateRSI(array $prices, int $period = 14): float
    {
        $count = count($prices);

        if ($count < $period + 1) {
            return 50.0; // neutral default
        }

        // Calculate price changes
        $changes = [];
        for ($i = 1; $i < $count; $i++) {
            $changes[] = $prices[$i] - $prices[$i - 1];
        }

        // Seed: initial average gain/loss using SMA of first $period changes
        $initialChanges = array_slice($changes, 0, $period);
        $gains  = array_map(fn($c) => max(0.0, $c), $initialChanges);
        $losses = array_map(fn($c) => max(0.0, -$c), $initialChanges);

        $avgGain = array_sum($gains) / $period;
        $avgLoss = array_sum($losses) / $period;

        // Wilder's smoothing for remaining changes
        $remaining = array_slice($changes, $period);
        foreach ($remaining as $change) {
            $gain    = max(0.0, $change);
            $loss    = max(0.0, -$change);
            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;
        }

        if ($avgLoss == 0.0) {
            return 100.0;
        }

        $rs = $avgGain / $avgLoss;

        return round(100 - (100 / (1 + $rs)), 2);
    }

    /**
     * Calculate all indicators from raw klines data.
     * Returns an array with ema_fast, ema_slow, rsi, current_price, is_bullish.
     */
    public function calculate(
        array $klines,
        int $emaFast = 20,
        int $emaSlow = 50,
        int $rsiPeriod = 14
    ): array {
        if (empty($klines)) {
            return [
                'ema_fast'      => 0.0,
                'ema_slow'      => 0.0,
                'rsi'           => 50.0,
                'current_price' => 0.0,
                'is_bullish'    => false,
                'valid'         => false,
            ];
        }

        $closes = array_map(fn($k) => (float) $k[4], $klines);

        $lastCandle  = end($klines);
        $currentPrice = (float) $lastCandle[4];
        $isBullish    = (float) $lastCandle[4] > (float) $lastCandle[1]; // close > open

        return [
            'ema_fast'      => $this->calculateEMA($closes, $emaFast),
            'ema_slow'      => $this->calculateEMA($closes, $emaSlow),
            'rsi'           => $this->calculateRSI($closes, $rsiPeriod),
            'current_price' => $currentPrice,
            'is_bullish'    => $isBullish,
            'valid'         => true,
        ];
    }
}
