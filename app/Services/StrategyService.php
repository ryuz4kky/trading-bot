<?php

namespace App\Services;

class StrategyService
{
    private const ADX_TREND_MIN  = 20.0;
    private const EMA_SPREAD_MIN = 0.15;

    /**
     * Analyze indicators and return signal: 'buy', 'sell', or 'hold'.
     * Strategy dipilih berdasarkan setting bot.
     */
    public function analyze(
        array  $indicators,
        string $strategy         = 'ema_crossover',
        float  $volumeMinRatio   = 1.2,
        int    $rsiBuyThreshold  = 35,
        int    $adxTrendThreshold = 25
    ): string {
        return $this->analyzeDetailed(
            $indicators,
            $strategy,
            $volumeMinRatio,
            $rsiBuyThreshold,
            $adxTrendThreshold
        )['signal'];
    }

    public function analyzeDetailed(
        array  $indicators,
        string $strategy         = 'ema_crossover',
        float  $volumeMinRatio   = 1.2,
        int    $rsiBuyThreshold  = 35,
        int    $adxTrendThreshold = 25
    ): array {
        if (! ($indicators['valid'] ?? false)) {
            return ['signal' => 'hold', 'score' => 0, 'setup' => null, 'scores' => []];
        }

        if ($strategy === 'adaptive') {
            return $this->adaptiveDetailed($indicators, $volumeMinRatio, $rsiBuyThreshold, $adxTrendThreshold);
        }

        $signal = match ($strategy) {
            'rsi_mean_reversion' => $this->rsiMeanReversion($indicators, $volumeMinRatio, $rsiBuyThreshold),
            'bb_squeeze'         => $this->bbSqueeze($indicators, $volumeMinRatio),
            default              => $this->emaCrossover($indicators, $volumeMinRatio, $rsiBuyThreshold, $adxTrendThreshold),
        };

        return [
            'signal' => $signal,
            'score'  => $signal === 'buy' ? 7 : 0,
            'setup'  => $strategy,
            'scores' => [],
        ];
    }

    /**
     * Deteksi regime market per pair lalu pilih strategi yang sesuai.
     *
     * ADX ≥ 25 + EMA spread lebar  → trending    → EMA Crossover
     * ADX < 20 + BB bandwidth sempit → squeeze    → BB Squeeze
     * ADX < 20                       → sideways   → RSI Mean Reversion
     * Di antara (20-25)              → ambiguous  → RSI Mean Reversion (lebih aman)
     */
    private function adaptive(array $ind, float $volumeMinRatio, int $rsiBuyThreshold, int $adxTrendThreshold): string
    {
        return $this->adaptiveDetailed($ind, $volumeMinRatio, $rsiBuyThreshold, $adxTrendThreshold)['signal'];
    }

    private function adaptiveDetailed(array $ind, float $volumeMinRatio, int $rsiBuyThreshold, int $adxTrendThreshold): array
    {
        if ($this->adaptiveSellSignal($ind)) {
            return ['signal' => 'sell', 'score' => 0, 'setup' => 'exit', 'scores' => []];
        }

        $scores = [
            'trend'   => $this->trendBuyScore($ind, $volumeMinRatio, $rsiBuyThreshold, $adxTrendThreshold),
            'mean'    => $this->meanReversionBuyScore($ind, $volumeMinRatio, $rsiBuyThreshold, $adxTrendThreshold),
            'squeeze' => $this->squeezeBuyScore($ind, $volumeMinRatio, $adxTrendThreshold),
        ];

        $bestScore = max($scores);
        $setup = array_search($bestScore, $scores, true) ?: null;

        return [
            'signal' => $bestScore >= 9 ? 'buy' : 'hold',
            'score'  => $bestScore,
            'setup'  => $setup,
            'scores' => $scores,
        ];
    }

    private function adaptiveSellSignal(array $ind): bool
    {
        $price    = (float) ($ind['current_price'] ?? 0.0);
        $emaFast  = (float) ($ind['ema_fast'] ?? 0.0);
        $emaSlow  = (float) ($ind['ema_slow'] ?? 0.0);
        $rsi      = (float) ($ind['rsi'] ?? 50.0);
        $bbUpper  = (float) ($ind['bb_upper'] ?? 0.0);

        if ($price <= 0) {
            return false;
        }

        $meanReversionExit = $bbUpper > 0 && $rsi >= 68 && $price >= $bbUpper * 0.985;
        $trendBreakExit = $emaFast > 0 && $emaSlow > 0 && $price < $emaSlow && $emaFast < $emaSlow && $rsi >= 62;

        return $meanReversionExit || $trendBreakExit;
    }

    private function trendBuyScore(array $ind, float $volumeMinRatio, int $rsiBuyThreshold, int $adxTrendThreshold): int
    {
        $price          = (float) ($ind['current_price'] ?? 0.0);
        $emaFast        = (float) ($ind['ema_fast'] ?? 0.0);
        $emaSlow        = (float) ($ind['ema_slow'] ?? 0.0);
        $prevEmaFast    = (float) ($ind['prev_ema_fast'] ?? $emaFast);
        $prevEmaSlow    = (float) ($ind['prev_ema_slow'] ?? $emaSlow);
        $rsi            = (float) ($ind['rsi'] ?? 50.0);
        $prevRsi        = (float) ($ind['prev_rsi'] ?? $rsi);
        $adx            = (float) ($ind['adx'] ?? 0.0);
        $emaSpreadPct   = (float) ($ind['ema_spread_pct'] ?? 0.0);
        $kama           = (float) ($ind['kama'] ?? 0.0);
        $prevKama       = (float) ($ind['prev_kama'] ?? $kama);
        $kamaSlopePct   = (float) ($ind['kama_slope_pct'] ?? 0.0);
        $cycleRsi       = (float) ($ind['cycle_rsi'] ?? $rsi);
        $prevCycleRsi   = (float) ($ind['prev_cycle_rsi'] ?? $cycleRsi);
        $macdHist       = (float) ($ind['macd_histogram'] ?? 0.0);
        $prevMacdHist   = (float) ($ind['prev_macd_histogram'] ?? $macdHist);
        $volumeRatio    = (float) ($ind['volume_ratio'] ?? 1.0);
        $bodyRatio      = (float) ($ind['candle_body_ratio'] ?? 0.0);
        $priceChangePct = abs((float) ($ind['price_change_pct'] ?? 0.0));
        $isBullish      = (bool) ($ind['is_bullish'] ?? false);

        if ($price <= 0 || $emaFast <= 0 || $emaSlow <= 0 || $rsi > 68 || $cycleRsi > 70 || $priceChangePct > 3.2) {
            return 0;
        }

        $score = 0;
        $score += $kama > 0 && $price > $kama ? 2 : 0;
        $score += $kama > 0 && $kama >= $prevKama ? 1 : 0;
        $score += $kamaSlopePct >= 0.02 ? 1 : 0;
        $score += $emaFast > $emaSlow ? 2 : 0;
        $score += $price > $emaFast ? 1 : 0;
        $score += $price > $emaSlow ? 1 : 0;
        $score += $emaFast >= $prevEmaFast && $emaSlow >= $prevEmaSlow ? 1 : 0;
        $score += $adx >= max(17, $adxTrendThreshold - 4) ? 1 : 0;
        $score += $emaSpreadPct >= 0.12 ? 1 : 0;
        $score += $cycleRsi >= max(40, $rsiBuyThreshold) && $cycleRsi <= 64 ? 2 : 0;
        $score += $cycleRsi >= ($prevCycleRsi - 1.0) ? 1 : 0;
        $score += $macdHist > 0 ? 2 : 0;
        $score += $macdHist >= $prevMacdHist ? 1 : 0;
        $score += $rsi >= ($prevRsi - 2.0) ? 1 : 0;
        $score += $isBullish ? 1 : 0;
        $score += $bodyRatio >= 0.25 ? 1 : 0;
        $score += $volumeRatio >= max(1.05, $volumeMinRatio) ? 1 : 0;

        return $score;
    }

    private function meanReversionBuyScore(array $ind, float $volumeMinRatio, int $rsiBuyThreshold, int $adxTrendThreshold): int
    {
        $price          = (float) ($ind['current_price'] ?? 0.0);
        $rsi            = (float) ($ind['rsi'] ?? 50.0);
        $prevRsi        = (float) ($ind['prev_rsi'] ?? $rsi);
        $adx            = (float) ($ind['adx'] ?? 0.0);
        $bbLower        = (float) ($ind['bb_lower'] ?? 0.0);
        $kama           = (float) ($ind['kama'] ?? 0.0);
        $kamaSlopePct   = (float) ($ind['kama_slope_pct'] ?? 0.0);
        $cycleRsi       = (float) ($ind['cycle_rsi'] ?? $rsi);
        $prevCycleRsi   = (float) ($ind['prev_cycle_rsi'] ?? $cycleRsi);
        $macdHist       = (float) ($ind['macd_histogram'] ?? 0.0);
        $prevMacdHist   = (float) ($ind['prev_macd_histogram'] ?? $macdHist);
        $bbPosition     = (float) ($ind['bb_position'] ?? 0.5);
        $bandwidth      = (float) ($ind['bb_bandwidth'] ?? 0.0);
        $volumeRatio    = (float) ($ind['volume_ratio'] ?? 1.0);
        $bodyRatio      = (float) ($ind['candle_body_ratio'] ?? 0.0);
        $priceChangePct = abs((float) ($ind['price_change_pct'] ?? 0.0));
        $isBullish      = (bool) ($ind['is_bullish'] ?? false);

        if ($price <= 0 || $bbLower <= 0 || $rsi > 48 || $cycleRsi > 50 || $priceChangePct > 2.4) {
            return 0;
        }

        $score = 0;
        $score += $kama > 0 && $price >= $kama * 0.985 ? 1 : 0;
        $score += $kamaSlopePct >= -0.05 ? 1 : 0;
        $score += $adx <= max(24, $adxTrendThreshold) ? 1 : 0;
        $score += $bandwidth >= 1.0 && $bandwidth <= 7.5 ? 1 : 0;
        $score += $cycleRsi <= max(38, $rsiBuyThreshold) ? 2 : 0;
        $score += $cycleRsi >= ($prevCycleRsi - 1.0) ? 1 : 0;
        $score += $macdHist >= $prevMacdHist ? 1 : 0;
        $score += $macdHist > 0 ? 1 : 0;
        $score += $rsi <= $rsiBuyThreshold ? 1 : 0;
        $score += $prevRsi <= ($rsi + 2.0) ? 1 : 0;
        $score += $price <= $bbLower * 1.012 ? 2 : 0;
        $score += $bbPosition <= 0.35 ? 1 : 0;
        $score += $isBullish ? 1 : 0;
        $score += $bodyRatio >= 0.22 ? 1 : 0;
        $score += $volumeRatio >= max(0.9, $volumeMinRatio - 0.25) ? 1 : 0;

        return $score;
    }

    private function squeezeBuyScore(array $ind, float $volumeMinRatio, int $adxTrendThreshold): int
    {
        $price          = (float) ($ind['current_price'] ?? 0.0);
        $rsi            = (float) ($ind['rsi'] ?? 50.0);
        $adx            = (float) ($ind['adx'] ?? 0.0);
        $bbMiddle       = (float) ($ind['bb_middle'] ?? 0.0);
        $kama           = (float) ($ind['kama'] ?? 0.0);
        $prevKama       = (float) ($ind['prev_kama'] ?? $kama);
        $cycleRsi       = (float) ($ind['cycle_rsi'] ?? $rsi);
        $macdHist       = (float) ($ind['macd_histogram'] ?? 0.0);
        $prevMacdHist   = (float) ($ind['prev_macd_histogram'] ?? $macdHist);
        $bandwidth      = (float) ($ind['bb_bandwidth'] ?? 0.0);
        $prevBandwidth  = (float) ($ind['prev_bb_bandwidth'] ?? $bandwidth);
        $volumeRatio    = (float) ($ind['volume_ratio'] ?? 1.0);
        $bodyRatio      = (float) ($ind['candle_body_ratio'] ?? 0.0);
        $priceChangePct = abs((float) ($ind['price_change_pct'] ?? 0.0));
        $isBullish      = (bool) ($ind['is_bullish'] ?? false);

        if ($price <= 0 || $bbMiddle <= 0 || $prevBandwidth <= 0 || $rsi > 68 || $cycleRsi > 68 || $priceChangePct > 3.0) {
            return 0;
        }

        $score = 0;
        $score += $kama > 0 && $price > $kama ? 2 : 0;
        $score += $kama > 0 && $kama >= $prevKama ? 1 : 0;
        $score += $bandwidth >= 1.4 ? 1 : 0;
        $score += $bandwidth >= $prevBandwidth * 1.02 ? 2 : 0;
        $score += $price > $bbMiddle ? 2 : 0;
        $score += $adx >= max(16, $adxTrendThreshold - 8) ? 1 : 0;
        $score += $cycleRsi >= 42 && $cycleRsi <= 66 ? 1 : 0;
        $score += $macdHist > 0 ? 1 : 0;
        $score += $macdHist >= $prevMacdHist ? 1 : 0;
        $score += $isBullish ? 1 : 0;
        $score += $bodyRatio >= 0.25 ? 1 : 0;
        $score += $volumeRatio >= max(1.05, $volumeMinRatio) ? 1 : 0;

        return $score;
    }

    // ─── Strategi 1: EMA Crossover + RSI (default) ───────────────────────────
    // Cocok untuk: trending market
    // BUY : ADX tinggi + EMA20 > EMA50 + harga > EMA50 + RSI 35-60 + bullish candle
    // SELL: EMA20 < EMA50 + harga < EMA50 + RSI > 65

    private function emaCrossover(array $ind, float $volumeMinRatio, int $rsiBuyThreshold = 35, int $adxTrendThreshold = 25): string
    {
        $price        = $ind['current_price'];
        $emaFast      = $ind['ema_fast'];
        $emaSlow      = $ind['ema_slow'];
        $rsi          = $ind['rsi'];
        $isBullish    = $ind['is_bullish'];
        $adx          = $ind['adx'] ?? 0.0;
        $emaSpreadPct = $ind['ema_spread_pct'] ?? 0.0;
        $volumeRatio  = $ind['volume_ratio'] ?? 1.0;
        $prevEmaFast  = $ind['prev_ema_fast'] ?? $emaFast;
        $prevEmaSlow  = $ind['prev_ema_slow'] ?? $emaSlow;
        $prevRsi      = $ind['prev_rsi'] ?? $rsi;
        $bodyRatio    = $ind['candle_body_ratio'] ?? 0.0;
        $priceChangePct = abs((float) ($ind['price_change_pct'] ?? 0.0));

        if ($emaFast <= 0 || $emaSlow <= 0) {
            return 'hold';
        }

        // Gunakan threshold dari settings user, bukan konstanta global
        $isTrending = ($adx >= $adxTrendThreshold) && ($emaSpreadPct >= self::EMA_SPREAD_MIN);
        $trendStrengthImproving = $emaFast >= $prevEmaFast && $emaSlow >= $prevEmaSlow;
        $rsiIsHealthy = $rsi >= max($rsiBuyThreshold, 38) && $rsi <= 60 && $rsi >= ($prevRsi - 1.5);
        $volumeConfirmed = $volumeRatio >= max($volumeMinRatio, 1.15);

        if ($isTrending && $price > $emaFast && $price > $emaSlow && $emaFast > $emaSlow
            && $trendStrengthImproving && $rsiIsHealthy && $isBullish
            && $bodyRatio >= 0.30 && $priceChangePct <= 2.4
            && $volumeConfirmed
        ) {
            return 'buy';
        }

        // EMA crossover sell via sinyal tidak digunakan (biarkan SL/TP)
        // Menghindari exit terlalu dini di tengah trend
        if ($price < $emaSlow && $emaFast < $emaSlow && $rsi > 70) {
            return 'sell';
        }

        return 'hold';
    }

    // ─── Strategi 2: RSI Mean Reversion + Bollinger Bands ────────────────────
    // Cocok untuk: sideways/ranging market (mayoritas waktu di crypto)
    // Win rate lebih tinggi karena harga selalu cenderung balik ke rata-rata
    // BUY : RSI < 35 + harga di bawah/dekat lower BB + bullish candle
    // SELL: RSI > 65 + harga di atas/dekat upper BB

    private function rsiMeanReversion(array $ind, float $volumeMinRatio, int $rsiBuyThreshold = 35): string
    {
        $price       = $ind['current_price'];
        $rsi         = $ind['rsi'];
        $bbLower     = $ind['bb_lower'] ?? 0.0;
        $bbUpper     = $ind['bb_upper'] ?? 0.0;
        $bbMiddle    = $ind['bb_middle'] ?? 0.0;
        $isBullish   = $ind['is_bullish'];
        $volumeRatio = $ind['volume_ratio'] ?? 1.0;
        $prevRsi     = $ind['prev_rsi'] ?? $rsi;
        $bbPosition  = $ind['bb_position'] ?? 0.5;
        $bodyRatio   = $ind['candle_body_ratio'] ?? 0.0;
        $priceChangePct = abs((float) ($ind['price_change_pct'] ?? 0.0));

        if ($bbLower <= 0 || $bbUpper <= 0) {
            return 'hold';
        }

        // BUY: oversold + harga dekat/di bawah lower band + reversal + volume konfirmasi
        // Syarat ketat: RSI harus benar-benar oversold (bukan sekadar rendah)
        if ($rsi <= $rsiBuyThreshold
            && $prevRsi <= ($rsi + 1.5)
            && $price <= $bbLower * 1.008
            && $bbPosition <= 0.30
            && $isBullish
            && $bodyRatio >= 0.25
            && $priceChangePct <= 1.8
            && $volumeRatio >= max(0.95, $volumeMinRatio - 0.2)
        ) {
            return 'buy';
        }

        // SELL via sinyal: hanya di upper BB yang jelas (99% dari upper)
        // Middle band sell dihapus — terlalu dini, makan fee tanpa profit memadai
        // Exit via TP/SL yang dihandle BotService lebih reliable
        if ($rsi > 68 && $price >= $bbUpper * 0.99) {
            return 'sell';
        }

        return 'hold';
    }

    // ─── Strategi 3: Bollinger Bands Squeeze ─────────────────────────────────
    // Cocok untuk: breakout setelah periode konsolidasi (BB menyempit)
    // Beli saat BB melebar setelah squeeze + harga breakout ke atas
    // BUY : BB bandwidth sempit lalu melebar + harga breakout atas middle + RSI < 60
    // SELL: harga menyentuh upper BB + RSI > 65

    private function bbSqueeze(array $ind, float $volumeMinRatio): string
    {
        $price       = $ind['current_price'];
        $rsi         = $ind['rsi'];
        $bbLower     = $ind['bb_lower'] ?? 0.0;
        $bbUpper     = $ind['bb_upper'] ?? 0.0;
        $bbMiddle    = $ind['bb_middle'] ?? 0.0;
        $isBullish   = $ind['is_bullish'];
        $adx         = $ind['adx'] ?? 0.0;
        $volumeRatio = $ind['volume_ratio'] ?? 1.0;
        $bandwidth   = $ind['bb_bandwidth'] ?? 0.0;
        $prevBandwidth = $ind['prev_bb_bandwidth'] ?? $bandwidth;
        $bodyRatio   = $ind['candle_body_ratio'] ?? 0.0;
        $priceChangePct = abs((float) ($ind['price_change_pct'] ?? 0.0));

        if ($bbLower <= 0 || $bbUpper <= 0 || $bbMiddle <= 0) {
            return 'hold';
        }

        // BUY: breakout ke atas middle BB + ADX mulai naik + RSI < 60 + bullish candle
        // Bandwidth > 2% berarti squeeze sudah mulai melebar (breakout terjadi)
        // Volume wajib tinggi saat breakout — squeeze tanpa volume = false breakout
        if ($prevBandwidth > 0 && $bandwidth > max(1.7, $prevBandwidth * 1.03)
            && $price > $bbMiddle && $adx >= 17
            && $rsi >= 42 && $rsi <= 62
            && $isBullish && $bodyRatio >= 0.30
            && $priceChangePct <= 2.4
            && $volumeRatio >= max($volumeMinRatio, 1.15)
        ) {
            return 'buy';
        }

        // SELL: harga menyentuh upper band + RSI overbought
        if ($price >= $bbUpper * 0.98 && $rsi > 65) {
            return 'sell';
        }

        return 'hold';
    }

    /**
     * Check if an open trade should be closed due to SL or TP.
     * Prioritas: gunakan stop_loss_price & take_profit_price dari trade jika tersedia.
     * Fallback ke persentase jika harga tidak valid.
     */
    public function checkExitCondition(
        float $entryPrice,
        float $currentPrice,
        float $stopLossPct,
        float $takeProfitPct,
        float $stopLossPrice   = 0,
        float $takeProfitPrice = 0,
    ): ?string {
        if ($entryPrice <= 0 || $currentPrice <= 0) {
            return null;
        }

        $slPrice = $stopLossPrice  > 0 ? $stopLossPrice  : $entryPrice * (1 - $stopLossPct / 100);
        $tpPrice = $takeProfitPrice > 0 ? $takeProfitPrice : $entryPrice * (1 + $takeProfitPct / 100);

        if ($currentPrice >= $tpPrice) {
            return 'take_profit';
        }

        if ($currentPrice <= $slPrice) {
            return 'stop_loss';
        }

        return null;
    }
}
