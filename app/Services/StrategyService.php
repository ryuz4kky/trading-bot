<?php

namespace App\Services;

class StrategyService
{
    /**
     * Analyze indicators and return signal: 'buy', 'sell', or 'hold'.
     *
     * BUY conditions:
     *   - Price above EMA slow (EMA50)
     *   - EMA fast (EMA20) > EMA slow (EMA50)  → uptrend
     *   - RSI between 40–60                     → not overbought, momentum building
     *   - Last candle is bullish
     *
     * SELL conditions:
     *   - Price below EMA slow (EMA50)
     *   - EMA fast (EMA20) < EMA slow (EMA50)  → downtrend
     *   - RSI > 65                              → overbought territory
     *
     * Otherwise → HOLD
     */
    // Minimum ADX untuk dianggap trending (bukan sideways)
    private const ADX_TREND_MIN    = 20.0;

    // Minimum spread EMA fast vs slow (dalam %) — di bawah ini = sideways
    private const EMA_SPREAD_MIN   = 0.15;

    public function analyze(array $indicators): string
    {
        if (! ($indicators['valid'] ?? false)) {
            return 'hold';
        }

        $price         = $indicators['current_price'];
        $emaFast       = $indicators['ema_fast'];
        $emaSlow       = $indicators['ema_slow'];
        $rsi           = $indicators['rsi'];
        $isBullish     = $indicators['is_bullish'];
        $adx           = $indicators['adx']           ?? 0.0;
        $emaSpreadPct  = $indicators['ema_spread_pct'] ?? 0.0;

        // Guard: invalid indicators
        if ($emaFast <= 0 || $emaSlow <= 0) {
            return 'hold';
        }

        // ── Market Condition Filter ───────────────────────────────────────────
        // Jika ADX rendah ATAU EMA terlalu rapat = market sideways/choppy → skip
        $isTrending = ($adx >= self::ADX_TREND_MIN) && ($emaSpreadPct >= self::EMA_SPREAD_MIN);

        // BUY signal — hanya jika market sedang trending
        if (
            $isTrending                       &&    // market trending, bukan sideways
            $price > $emaSlow                 &&    // price above EMA50
            $emaFast > $emaSlow               &&    // EMA20 crossed above EMA50
            $rsi >= 35 && $rsi <= 60          &&    // RSI in buy zone (sedikit lebih selektif)
            $isBullish                               // bullish candle confirmation
        ) {
            return 'buy';
        }

        // SELL signal — tidak perlu filter trending untuk exit
        if (
            $price < $emaSlow   &&    // price below EMA50
            $emaFast < $emaSlow &&    // EMA20 below EMA50 (downtrend)
            $rsi > 65                 // RSI overbought
        ) {
            return 'sell';
        }

        return 'hold';
    }

    /**
     * Check if an open trade should be closed due to SL or TP.
     * Returns: 'take_profit' | 'stop_loss' | null
     */
    public function checkExitCondition(
        float $entryPrice,
        float $currentPrice,
        float $stopLossPct,
        float $takeProfitPct
    ): ?string {
        if ($entryPrice <= 0) {
            return null;
        }

        $changePercent = (($currentPrice - $entryPrice) / $entryPrice) * 100;

        if ($changePercent >= $takeProfitPct) {
            return 'take_profit';
        }

        if ($changePercent <= -$stopLossPct) {
            return 'stop_loss';
        }

        return null;
    }
}
