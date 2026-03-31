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
    public function analyze(array $indicators, string $strategy = 'ema_crossover', float $volumeMinRatio = 1.2): string
    {
        if (! ($indicators['valid'] ?? false)) {
            return 'hold';
        }

        return match ($strategy) {
            'rsi_mean_reversion' => $this->rsiMeanReversion($indicators, $volumeMinRatio),
            'bb_squeeze'         => $this->bbSqueeze($indicators, $volumeMinRatio),
            default              => $this->emaCrossover($indicators, $volumeMinRatio),
        };
    }

    // ─── Strategi 1: EMA Crossover + RSI (default) ───────────────────────────
    // Cocok untuk: trending market
    // BUY : ADX tinggi + EMA20 > EMA50 + harga > EMA50 + RSI 35-60 + bullish candle
    // SELL: EMA20 < EMA50 + harga < EMA50 + RSI > 65

    private function emaCrossover(array $ind, float $volumeMinRatio): string
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

        $isTrending = ($adx >= self::ADX_TREND_MIN) && ($emaSpreadPct >= self::EMA_SPREAD_MIN);

        if ($isTrending && $price > $emaSlow && $emaFast > $emaSlow
            && $rsi >= 35 && $rsi <= 60 && $isBullish
            && $volumeRatio >= $volumeMinRatio
        ) {
            return 'buy';
        }

        if ($price < $emaSlow && $emaFast < $emaSlow && $rsi > 65) {
            return 'sell';
        }

        return 'hold';
    }

    // ─── Strategi 2: RSI Mean Reversion + Bollinger Bands ────────────────────
    // Cocok untuk: sideways/ranging market (mayoritas waktu di crypto)
    // Win rate lebih tinggi karena harga selalu cenderung balik ke rata-rata
    // BUY : RSI < 35 + harga di bawah/dekat lower BB + bullish candle
    // SELL: RSI > 65 + harga di atas/dekat upper BB

    private function rsiMeanReversion(array $ind, float $volumeMinRatio): string
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

        // BUY: oversold + harga menyentuh/melewati lower band + reversal + volume konfirmasi
        if ($rsi < 35 && $price <= $bbLower * 1.01 && $isBullish && $volumeRatio >= $volumeMinRatio) {
            return 'buy';
        }

        // SELL: overbought + harga menyentuh upper band
        // ATAU harga kembali ke middle band setelah buy dari bawah
        if (($rsi > 65 && $price >= $bbUpper * 0.99) || ($rsi > 55 && $price >= $bbMiddle)) {
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
