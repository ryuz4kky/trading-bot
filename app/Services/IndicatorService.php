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

    public function calculateKAMA(array $prices, int $erPeriod = 10, int $fastPeriod = 2, int $slowPeriod = 30): float
    {
        $count = count($prices);

        if ($count < $erPeriod + 1) {
            return 0.0;
        }

        $fastSC = 2 / ($fastPeriod + 1);
        $slowSC = 2 / ($slowPeriod + 1);
        $kama = array_sum(array_slice($prices, 0, $erPeriod)) / $erPeriod;

        for ($i = $erPeriod; $i < $count; $i++) {
            $change = abs($prices[$i] - $prices[$i - $erPeriod]);
            $volatility = 0.0;

            for ($j = $i - $erPeriod + 1; $j <= $i; $j++) {
                $volatility += abs($prices[$j] - $prices[$j - 1]);
            }

            $er = $volatility > 0 ? $change / $volatility : 0.0;
            $sc = ($er * ($fastSC - $slowSC) + $slowSC) ** 2;
            $kama = $kama + $sc * ($prices[$i] - $kama);
        }

        return round($kama, 8);
    }

    public function estimateCyclePeriod(array $prices, int $minPeriod = 10, int $maxPeriod = 30): int
    {
        $count = count($prices);

        if ($count < $maxPeriod + 2) {
            return 14;
        }

        $returns = [];
        for ($i = 1; $i < $count; $i++) {
            $returns[] = $prices[$i] - $prices[$i - 1];
        }

        $bestLag = 14;
        $bestCorr = -1.0;
        $sampleSize = min(60, count($returns) - $maxPeriod);

        if ($sampleSize < $minPeriod) {
            return 14;
        }

        for ($lag = $minPeriod; $lag <= $maxPeriod; $lag++) {
            $recent = array_slice($returns, -$sampleSize);
            $shifted = array_slice($returns, -$sampleSize - $lag, $sampleSize);
            $corr = abs($this->correlation($recent, $shifted));

            if ($corr > $bestCorr) {
                $bestCorr = $corr;
                $bestLag = $lag;
            }
        }

        return $bestLag;
    }

    private function correlation(array $a, array $b): float
    {
        $n = min(count($a), count($b));

        if ($n <= 1) {
            return 0.0;
        }

        $a = array_slice($a, 0, $n);
        $b = array_slice($b, 0, $n);
        $avgA = array_sum($a) / $n;
        $avgB = array_sum($b) / $n;
        $num = 0.0;
        $denA = 0.0;
        $denB = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $da = $a[$i] - $avgA;
            $db = $b[$i] - $avgB;
            $num += $da * $db;
            $denA += $da ** 2;
            $denB += $db ** 2;
        }

        $den = sqrt($denA * $denB);

        return $den > 0 ? $num / $den : 0.0;
    }

    public function calculateMACD(array $prices, int $fastPeriod = 12, int $slowPeriod = 26, int $signalPeriod = 9): array
    {
        $count = count($prices);
        $minCount = max($slowPeriod + $signalPeriod, $fastPeriod + $signalPeriod);

        if ($count < $minCount) {
            return ['macd' => 0.0, 'signal' => 0.0, 'histogram' => 0.0, 'prev_histogram' => 0.0];
        }

        $macdLine = [];
        for ($i = $slowPeriod; $i <= $count; $i++) {
            $slice = array_slice($prices, 0, $i);
            $macdLine[] = $this->calculateEMA($slice, $fastPeriod) - $this->calculateEMA($slice, $slowPeriod);
        }

        $signal = $this->calculateEMA($macdLine, $signalPeriod);
        $macd = end($macdLine) ?: 0.0;
        $prevMacd = count($macdLine) > 1 ? $macdLine[count($macdLine) - 2] : $macd;
        $prevSignal = count($macdLine) > $signalPeriod
            ? $this->calculateEMA(array_slice($macdLine, 0, -1), $signalPeriod)
            : $signal;

        return [
            'macd'           => round($macd, 8),
            'signal'         => round($signal, 8),
            'histogram'      => round($macd - $signal, 8),
            'prev_histogram' => round($prevMacd - $prevSignal, 8),
        ];
    }

    public function calculateChandelierExit(array $klines, int $period = 22, float $atrMultiplier = 3.0): array
    {
        if (count($klines) < $period + 1) {
            return ['long' => 0.0, 'short' => 0.0];
        }

        $slice = array_slice($klines, -$period);
        $highestHigh = max(array_map(fn($k) => (float) $k[2], $slice));
        $lowestLow = min(array_map(fn($k) => (float) $k[3], $slice));
        $atr = $this->calculateATR($klines, min(22, $period));

        if ($atr <= 0) {
            return ['long' => 0.0, 'short' => 0.0];
        }

        return [
            'long'  => round($highestHigh - ($atr * $atrMultiplier), 8),
            'short' => round($lowestLow + ($atr * $atrMultiplier), 8),
        ];
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
        $cyclePeriod = $this->estimateCyclePeriod($closes);
        $cycleRsi = $this->calculateRSI($closes, $cyclePeriod);
        $prevCycleRsi = count($closes) > $cyclePeriod + 1
            ? $this->calculateRSI(array_slice($closes, 0, -1), $cyclePeriod)
            : $cycleRsi;
        $kamaPeriod = max(10, min(20, (int) round($cyclePeriod * 0.75)));
        $kama = $this->calculateKAMA($closes, $kamaPeriod);
        $prevKama = count($closes) > $kamaPeriod + 1
            ? $this->calculateKAMA(array_slice($closes, 0, -1), $kamaPeriod)
            : $kama;
        $macdFast = max(5, (int) round($cyclePeriod / 2));
        $macdSlow = max($macdFast + 2, $cyclePeriod);
        $macdSignal = max(4, (int) round($cyclePeriod / 3));
        $macd = $this->calculateMACD($closes, $macdFast, $macdSlow, $macdSignal);
        $chandelierPeriod = max(14, min(30, $cyclePeriod));
        $chandelier = $this->calculateChandelierExit($klines, $chandelierPeriod, 3.0);
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
            'cycle_period'   => $cyclePeriod,
            'cycle_rsi'      => $cycleRsi,
            'prev_cycle_rsi' => $prevCycleRsi,
            'kama'           => $kama,
            'prev_kama'      => $prevKama,
            'kama_slope_pct' => $prevKama > 0 ? round((($kama - $prevKama) / $prevKama) * 100, 4) : 0.0,
            'macd'           => $macd['macd'],
            'macd_signal'    => $macd['signal'],
            'macd_histogram' => $macd['histogram'],
            'prev_macd_histogram' => $macd['prev_histogram'],
            'chandelier_long' => $chandelier['long'],
            'chandelier_short' => $chandelier['short'],
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
