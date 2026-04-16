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
        if (! ($indicators['valid'] ?? false)) {
            return 'hold';
        }

        if ($strategy === 'adaptive') {
            return $this->adaptive($indicators, $volumeMinRatio, $rsiBuyThreshold, $adxTrendThreshold);
        }

        return match ($strategy) {
            'rsi_mean_reversion' => $this->rsiMeanReversion($indicators, $volumeMinRatio, $rsiBuyThreshold),
            'bb_squeeze'         => $this->bbSqueeze($indicators, $volumeMinRatio),
            default              => $this->emaCrossover($indicators, $volumeMinRatio, $rsiBuyThreshold, $adxTrendThreshold),
        };
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
        $adx          = $ind['adx']           ?? 0.0;
        $emaSpreadPct = $ind['ema_spread_pct'] ?? 0.0;
        $bbUpper      = $ind['bb_upper']       ?? 0.0;
        $bbLower      = $ind['bb_lower']       ?? 0.0;
        $bbMiddle     = $ind['bb_middle']      ?? 0.0;

        $bandwidth   = ($bbMiddle > 0) ? (($bbUpper - $bbLower) / $bbMiddle) * 100 : 0;
        $adxSideways = max(15, $adxTrendThreshold - 5);

        // Trending: ADX kuat + EMA spread cukup besar
        if ($adx >= $adxTrendThreshold && $emaSpreadPct >= self::EMA_SPREAD_MIN) {
            return $this->emaCrossover($ind, $volumeMinRatio, $rsiBuyThreshold, $adxTrendThreshold);
        }

        // Squeeze forming: market konsolidasi sempit, siap breakout
        if ($adx < $adxSideways && $bandwidth < 3.0) {
            return $this->bbSqueeze($ind, $volumeMinRatio);
        }

        // Sideways / ambiguous: mean reversion paling aman
        return $this->rsiMeanReversion($ind, $volumeMinRatio, $rsiBuyThreshold);
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

        if ($emaFast <= 0 || $emaSlow <= 0) {
            return 'hold';
        }

        // Gunakan threshold dari settings user, bukan konstanta global
        $isTrending = ($adx >= $adxTrendThreshold) && ($emaSpreadPct >= self::EMA_SPREAD_MIN);

        if ($isTrending && $price > $emaSlow && $emaFast > $emaSlow
            && $rsi >= $rsiBuyThreshold && $rsi <= 58 && $isBullish
            && $volumeRatio >= $volumeMinRatio
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

        if ($bbLower <= 0 || $bbUpper <= 0) {
            return 'hold';
        }

        // BUY: oversold + harga dekat/di bawah lower band + reversal + volume konfirmasi
        // Syarat ketat: RSI harus benar-benar oversold (bukan sekadar rendah)
        if ($rsi < $rsiBuyThreshold && $price <= $bbLower * 1.005 && $isBullish && $volumeRatio >= $volumeMinRatio) {
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

        if ($bbLower <= 0 || $bbUpper <= 0 || $bbMiddle <= 0) {
            return 'hold';
        }

        // Bandwidth BB sebagai % dari middle (squeeze = nilai kecil)
        $bandwidth = (($bbUpper - $bbLower) / $bbMiddle) * 100;

        // BUY: breakout ke atas middle BB + ADX mulai naik + RSI < 60 + bullish candle
        // Bandwidth > 2% berarti squeeze sudah mulai melebar (breakout terjadi)
        // Volume wajib tinggi saat breakout — squeeze tanpa volume = false breakout
        if ($bandwidth > 2.0 && $price > $bbMiddle && $adx >= 18
            && $rsi < 60 && $isBullish && $volumeRatio >= $volumeMinRatio
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
