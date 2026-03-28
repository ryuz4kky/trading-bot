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
     * Calculate Average True Range (ATR) — measures volatility.
     * Higher ATR = more volatile = needs wider SL.
     */
    public function calculateATR(array $klines, int $period = 14): float
    {
        $count = count($klines);

        if ($count < $period + 1) {
            return 0.0;
        }

        $trueRanges = [];
        for ($i = 1; $i < $count; $i++) {
            $high  = (float) $klines[$i][2];
            $low   = (float) $klines[$i][3];
            $prevClose = (float) $klines[$i - 1][4];

            $trueRanges[] = max(
                $high - $low,
                abs($high - $prevClose),
                abs($low  - $prevClose)
            );
        }

        // Seed: SMA of first $period TRs
        $atr = array_sum(array_slice($trueRanges, 0, $period)) / $period;

        // Wilder's smoothing
        foreach (array_slice($trueRanges, $period) as $tr) {
            $atr = (($atr * ($period - 1)) + $tr) / $period;
        }

        return round($atr, 8);
    }

    /**
     * Calculate ADX (Average Directional Index) — measures trend strength.
     * ADX > 25 = trending market (safe to trade).
     * ADX < 20 = sideways/choppy (avoid entry).
     */
    public function calculateADX(array $klines, int $period = 14): float
    {
        $count = count($klines);

        if ($count < $period * 2) {
            return 0.0;
        }

        $dmPlus  = [];
        $dmMinus = [];
        $trList  = [];

        for ($i = 1; $i < $count; $i++) {
            $high      = (float) $klines[$i][2];
            $low       = (float) $klines[$i][3];
            $prevHigh  = (float) $klines[$i - 1][2];
            $prevLow   = (float) $klines[$i - 1][3];
            $prevClose = (float) $klines[$i - 1][4];

            $upMove   = $high - $prevHigh;
            $downMove = $prevLow - $low;

            $dmPlus[]  = ($upMove > $downMove && $upMove > 0) ? $upMove : 0.0;
            $dmMinus[] = ($downMove > $upMove && $downMove > 0) ? $downMove : 0.0;
            $trList[]  = max($high - $low, abs($high - $prevClose), abs($low - $prevClose));
        }

        // Wilder's smoothed sums (seed = sum of first $period)
        $smoothTR    = array_sum(array_slice($trList,  0, $period));
        $smoothDMp   = array_sum(array_slice($dmPlus,  0, $period));
        $smoothDMm   = array_sum(array_slice($dmMinus, 0, $period));

        $dxValues = [];

        for ($i = $period; $i < count($trList); $i++) {
            $smoothTR  = $smoothTR  - ($smoothTR  / $period) + $trList[$i];
            $smoothDMp = $smoothDMp - ($smoothDMp / $period) + $dmPlus[$i];
            $smoothDMm = $smoothDMm - ($smoothDMm / $period) + $dmMinus[$i];

            if ($smoothTR == 0) continue;

            $diPlus  = ($smoothDMp / $smoothTR) * 100;
            $diMinus = ($smoothDMm / $smoothTR) * 100;
            $diSum   = $diPlus + $diMinus;

            if ($diSum == 0) continue;

            $dxValues[] = abs($diPlus - $diMinus) / $diSum * 100;
        }

        if (empty($dxValues)) {
            return 0.0;
        }

        // ADX = EMA of DX values
        $adx = array_sum(array_slice($dxValues, 0, $period)) / min($period, count($dxValues));
        foreach (array_slice($dxValues, $period) as $dx) {
            $adx = (($adx * ($period - 1)) + $dx) / $period;
        }

        return round($adx, 2);
    }

    /**
     * Calculate all indicators from raw klines data.
     * Returns ema_fast, ema_slow, rsi, atr, adx, ema_spread_pct, current_price, is_bullish.
     */
    public function calculate(
        array $klines,
        int $emaFast = 20,
        int $emaSlow = 50,
        int $rsiPeriod = 14
    ): array {
        if (empty($klines)) {
            return [
                'ema_fast'       => 0.0,
                'ema_slow'       => 0.0,
                'rsi'            => 50.0,
                'atr'            => 0.0,
                'adx'            => 0.0,
                'ema_spread_pct' => 0.0,
                'current_price'  => 0.0,
                'is_bullish'     => false,
                'valid'          => false,
            ];
        }

        $closes = array_map(fn($k) => (float) $k[4], $klines);

        $lastCandle   = end($klines);
        $currentPrice = (float) $lastCandle[4];
        $isBullish    = (float) $lastCandle[4] > (float) $lastCandle[1];

        $emaFastVal = $this->calculateEMA($closes, $emaFast);
        $emaSlowVal = $this->calculateEMA($closes, $emaSlow);

        // Spread antar EMA sebagai % — kecil berarti sideways
        $emaSpreadPct = $emaSlowVal > 0
            ? round(abs($emaFastVal - $emaSlowVal) / $emaSlowVal * 100, 4)
            : 0.0;

        return [
            'ema_fast'       => $emaFastVal,
            'ema_slow'       => $emaSlowVal,
            'rsi'            => $this->calculateRSI($closes, $rsiPeriod),
            'atr'            => $this->calculateATR($klines),
            'adx'            => $this->calculateADX($klines),
            'ema_spread_pct' => $emaSpreadPct,
            'current_price'  => $currentPrice,
            'is_bullish'     => $isBullish,
            'valid'          => true,
        ];
    }
}
