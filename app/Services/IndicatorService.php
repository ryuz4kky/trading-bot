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
     * Calculate Bollinger Bands (SMA ± 2×StdDev).
     * Returns ['upper' => float, 'middle' => float, 'lower' => float]
     */
    public function calculateBollingerBands(array $prices, int $period = 20): array
    {
        $count = count($prices);

        if ($count < $period) {
            return ['upper' => 0.0, 'middle' => 0.0, 'lower' => 0.0];
        }

        $slice  = array_slice($prices, -$period);
        $middle = array_sum($slice) / $period;

        $variance = array_sum(array_map(fn($p) => ($p - $middle) ** 2, $slice)) / $period;
        $stdDev   = sqrt($variance);

        return [
            'upper'  => round($middle + 2 * $stdDev, 8),
            'middle' => round($middle, 8),
            'lower'  => round($middle - 2 * $stdDev, 8),
        ];
    }

    /**
     * Calculate all indicators from raw klines data.
     * Returns ema_fast, ema_slow, rsi, atr, adx, ema_spread_pct, bb_*, current_price, is_bullish.
     */
    public function calculate(
        array $klines,
        int $emaFast = 20,
        int $emaSlow = 50,
        int $rsiPeriod = 14,
        int $bbPeriod = 20
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

        // Bullish candle: close > open DAN body setidaknya 30% dari total range candle
        // Mencegah doji (body sangat kecil) dianggap bullish — sinyal yang tidak bermakna
        $candleOpen  = (float) $lastCandle[1];
        $candleHigh  = (float) $lastCandle[2];
        $candleLow   = (float) $lastCandle[3];
        $candleRange = $candleHigh - $candleLow;
        $candleBody  = abs($currentPrice - $candleOpen);
        $isBullish   = $currentPrice > $candleOpen
            && ($candleRange <= 0 || ($candleBody / $candleRange) >= 0.30);

        $emaFastVal = $this->calculateEMA($closes, $emaFast);
        $emaSlowVal = $this->calculateEMA($closes, $emaSlow);
        $prevEmaFast = count($closes) > $emaFast
            ? $this->calculateEMA(array_slice($closes, 0, -1), $emaFast)
            : $emaFastVal;
        $prevEmaSlow = count($closes) > $emaSlow
            ? $this->calculateEMA(array_slice($closes, 0, -1), $emaSlow)
            : $emaSlowVal;

        $emaSpreadPct = $emaSlowVal > 0
            ? round(abs($emaFastVal - $emaSlowVal) / $emaSlowVal * 100, 4)
            : 0.0;
        $priceChangePct = count($closes) > 1 && $closes[count($closes) - 2] > 0
            ? round((($currentPrice - $closes[count($closes) - 2]) / $closes[count($closes) - 2]) * 100, 4)
            : 0.0;

        $bb = $this->calculateBollingerBands($closes, $bbPeriod);
        $prevBb = count($closes) > $bbPeriod
            ? $this->calculateBollingerBands(array_slice($closes, 0, -1), $bbPeriod)
            : $bb;

        // Volume ratio: volume candle terakhir dibanding rata-rata 20 candle SEBELUMNYA
        // Candle terakhir dikecualikan dari rata-rata agar tidak inflasi diri sendiri
        $volumes    = array_map(fn($k) => (float) $k[5], $klines);
        $lastVolume = end($volumes);
        $volCount   = count($volumes);
        // Ambil 20 candle sebelum candle terakhir
        $volSlice   = $volCount >= 21
            ? array_slice($volumes, -21, 20)
            : array_slice($volumes, 0, $volCount - 1);
        $avgVolume   = count($volSlice) > 0 ? array_sum($volSlice) / count($volSlice) : 0;
        $volumeRatio = $avgVolume > 0 ? round($lastVolume / $avgVolume, 2) : 1.0;
        $rsiNow      = $this->calculateRSI($closes, $rsiPeriod);
        $prevRsi     = count($closes) > $rsiPeriod + 1
            ? $this->calculateRSI(array_slice($closes, 0, -1), $rsiPeriod)
            : $rsiNow;
        $bbRange     = max(0.00000001, $bb['upper'] - $bb['lower']);
        $bbPosition  = round(($currentPrice - $bb['lower']) / $bbRange, 4);
        $prevBbMiddle = $prevBb['middle'] ?? 0.0;
        $prevBandwidth = $prevBbMiddle > 0
            ? round((($prevBb['upper'] - $prevBb['lower']) / $prevBbMiddle) * 100, 4)
            : 0.0;
        $currentBandwidth = ($bb['middle'] ?? 0) > 0
            ? round((($bb['upper'] - $bb['lower']) / $bb['middle']) * 100, 4)
            : 0.0;

        return [
            'ema_fast'       => $emaFastVal,
            'ema_slow'       => $emaSlowVal,
            'prev_ema_fast'  => $prevEmaFast,
            'prev_ema_slow'  => $prevEmaSlow,
            'rsi'            => $rsiNow,
            'prev_rsi'       => $prevRsi,
            'atr'            => $this->calculateATR($klines),
            'adx'            => $this->calculateADX($klines),
            'ema_spread_pct' => $emaSpreadPct,
            'bb_upper'       => $bb['upper'],
            'bb_middle'      => $bb['middle'],
            'bb_lower'       => $bb['lower'],
            'bb_position'    => $bbPosition,
            'bb_bandwidth'   => $currentBandwidth,
            'prev_bb_bandwidth' => $prevBandwidth,
            'current_price'  => $currentPrice,
            'price_change_pct' => $priceChangePct,
            'is_bullish'     => $isBullish,
            'candle_body_ratio' => $candleRange > 0 ? round($candleBody / $candleRange, 4) : 0.0,
            'volume_ratio'   => $volumeRatio,
            'valid'          => true,
        ];
    }
}
