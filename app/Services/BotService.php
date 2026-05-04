<?php

namespace App\Services;

use App\Models\Balance;
use App\Models\Bot;
use App\Models\BotLog;
use App\Models\Trade;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BotService
{
    public function __construct(
        private BinanceService   $binance,
        private IndicatorService $indicator,
        private StrategyService  $strategy,
    ) {}

    // ─── Main Entry Point ─────────────────────────────────────────────────────

    public function run(Bot $bot): void
    {
        if (! $bot->isRunning()) {
            return;
        }

        if (! $bot->settings) {
            $this->log($bot, 'error', 'Bot settings tidak ditemukan.');
            return;
        }

        $portfolio = $this->getPortfolioSnapshot($bot);
        $this->log($bot, 'info', sprintf(
            'Bot run dimulai. Modal: Rp %s | Posisi: %d/%d | Bebas: Rp %s',
            number_format($portfolio['total_capital'], 0, ',', '.'),
            $portfolio['open_count'],
            $portfolio['max_positions'],
            number_format($portfolio['free_idr'], 0, ',', '.')
        ));

        if ($this->isMaxDailyLossReached($bot, $portfolio['total_capital'])) {
            $this->log($bot, 'warning', 'Max daily loss tercapai. Bot berhenti membuka posisi baru hari ini.');
            $this->monitorPositions($bot);
            return;
        }

        $this->monitorPositions($bot);

        $this->processBestEntry($bot);

        $this->log($bot, 'info', 'Bot run selesai.');
    }

    // ─── Portfolio Snapshot ───────────────────────────────────────────────────

    /**
     * Snapshot kondisi portofolio saat ini.
     *
     * Konsep Full Balance System:
     * total_capital = free_idr + nilai IDR semua posisi terbuka.
     * per_position  = total_capital / max_positions.
     */
    public function getPortfolioSnapshot(Bot $bot): array
    {
        $settings     = $bot->settings;
        $maxPositions = (int) ($settings?->max_positions ?? 4);

        $freeIdr = $this->getAvailableIdr($bot);

        // Nilai modal yang sedang "dipakai" di posisi terbuka (filter mode agar sim/real tidak campur)
        $lockedIdr = (float) Trade::where('bot_id', $bot->id)
            ->where('status', 'open')
            ->where('mode', $bot->mode)
            ->sum('amount_idr');

        $totalCapital     = $freeIdr + $lockedIdr;
        $perPositionAlloc = $maxPositions > 0 ? $totalCapital / $maxPositions : $totalCapital;
        $openCount        = Trade::where('bot_id', $bot->id)->where('status', 'open')->where('mode', $bot->mode)->count();
        $availableSlots   = max(0, $maxPositions - $openCount);

        return [
            'total_capital'     => $totalCapital,
            'free_idr'          => $freeIdr,
            'locked_idr'        => $lockedIdr,
            'per_position_alloc' => round($perPositionAlloc),
            'max_positions'     => $maxPositions,
            'open_count'        => $openCount,
            'available_slots'   => $availableSlots,
            'utilization_pct'   => $totalCapital > 0 ? round(($lockedIdr / $totalCapital) * 100, 1) : 0,
        ];
    }

    // ─── Scanning ─────────────────────────────────────────────────────────────

    public function scanPair(Bot $bot, string $binancePair): array
    {
        $settings = $bot->settings;
        $base = [
            'pair'         => $binancePair,
            'display_pair' => $this->binance->getDisplayName($binancePair),
            'indodax_pair' => $this->binance->mapToIndodaxPair($binancePair),
            'signal'       => 'hold',
            'signal_score' => 0,
            'signal_setup' => null,
            'signal_scores' => [],
            'indicators'   => [],
            'idr_price'    => 0,
            'error'        => null,
        ];

        $klines = $this->binance->getKlines(
            $binancePair,
            $settings->kline_interval ?? '5m',
            $this->resolveKlineLimit($settings)
        );

        if (empty($klines)) {
            $base['error'] = 'Gagal mengambil data kline dari Binance. Periksa koneksi internet.';
            return $base;
        }

        $indicators = $this->indicator->calculate(
            $klines,
            $settings->ema_fast   ?? 20,
            $settings->ema_slow   ?? 50,
            $settings->rsi_period ?? 14,
            $settings->bb_period  ?? 20
        );

        $idrPrice = $this->binance->getIdrPrice($binancePair);
        if ($idrPrice <= 0) {
            $idrPrice = round(($indicators['current_price'] ?? 0) * 16000);
        }

        $analysis = $this->strategy->analyzeDetailed(
            $indicators,
            $bot->settings?->strategy        ?? 'ema_crossover',
            (float) ($bot->settings?->volume_min_ratio   ?? 1.2),
            (int)   ($bot->settings?->rsi_buy_threshold  ?? 35),
            (int)   ($bot->settings?->adx_trend_threshold ?? 25)
        );

        // Deteksi regime untuk adaptive (info saja)
        $regime = null;
        if (($bot->settings?->strategy ?? '') === 'adaptive') {
            $adx       = $indicators['adx'] ?? 0;
            $spread    = $indicators['ema_spread_pct'] ?? 0;
            $bandwidth = $indicators['bb_bandwidth'] ?? 0;

            $regime = match (true) {
                $adx >= ($settings->adx_trend_threshold ?? 25) && $spread >= 0.15 => 'trending→EMA',
                $adx >= 17 && $adx < ($settings->adx_trend_threshold ?? 25) && $bandwidth >= 1.7 => 'squeeze→BB',
                $adx < ($settings->adx_trend_threshold ?? 25) && $bandwidth >= 1.2 && $bandwidth <= 6.5 => 'sideways→RSI',
                $spread >= 0.15 => 'ambiguous→EMA',
                default => 'ambiguous→HOLD',
            };
        }

        return array_merge($base, [
            'signal'        => $analysis['signal'],
            'signal_score'  => $analysis['score'],
            'signal_setup'  => $analysis['setup'],
            'signal_scores' => $analysis['scores'],
            'indicators'    => $indicators,
            'idr_price'     => $idrPrice,
            'regime'        => $regime,
        ]);
    }

    public function scanAll(Bot $bot): array
    {
        if (! $bot->settings || empty($bot->settings->pairs)) {
            return [];
        }

        return array_map(fn($pair) => $this->scanPair($bot, $pair), $bot->settings->pairs);
    }

    // ─── Trade Processing ──────────────────────────────────────────────────────

    private function processBestEntry(Bot $bot): void
    {
        if (! $bot->settings || empty($bot->settings->pairs)) {
            return;
        }

        $openCount = Trade::where('bot_id', $bot->id)
            ->where('status', 'open')
            ->where('mode', $bot->mode)
            ->count();

        if ($openCount >= (int) ($bot->settings->max_positions ?? 1)) {
            $this->log($bot, 'info', 'Slot posisi penuh. Tidak membuka entry baru.');
            return;
        }

        $candidates = [];

        foreach ($bot->settings->pairs as $binancePair) {
            $scan = $this->scanPair($bot, $binancePair);

            if ($scan['error'] || $scan['idr_price'] <= 0) {
                continue;
            }

            $openTrade = Trade::where('bot_id', $bot->id)
                ->where('binance_pair', $binancePair)
                ->where('status', 'open')
                ->where('mode', $bot->mode)
                ->first();

            if ($scan['signal'] === 'sell' && $openTrade) {
                $this->processSignalSell($bot, $openTrade, $scan);
                continue;
            }

            if ($scan['signal'] !== 'buy' || $openTrade || $this->isInCooldown($bot, $binancePair)) {
                continue;
            }

            $candidates[] = $scan;
        }

        if (empty($candidates)) {
            $this->log($bot, 'info', 'Tidak ada kandidat entry yang lolos scoring.');
            return;
        }

        usort($candidates, fn(array $a, array $b) => ($b['signal_score'] <=> $a['signal_score']));

        $best = $candidates[0];

        $this->log($bot, 'info', sprintf(
            'Best entry dipilih: %s | score: %s | setup: %s | regime: %s',
            $best['pair'],
            $best['signal_score'],
            $best['signal_setup'] ?? '-',
            $best['regime'] ?? '-'
        ));

        $this->executeBuy(
            $bot,
            $best['pair'],
            $best['indodax_pair'],
            $best['idr_price'],
            $best['indicators']
        );
    }

    private function processSignalSell(Bot $bot, Trade $openTrade, array $scan): void
    {
        $strategy = $bot->settings?->strategy ?? 'ema_crossover';
        $entryPrice = (float) $openTrade->entry_price;
        $profitPct = $entryPrice > 0 ? (($scan['idr_price'] - $entryPrice) / $entryPrice) * 100 : 0;
        $minProfitToSell = 1.2;

        if ($profitPct < $minProfitToSell) {
            return;
        }

        if (in_array($strategy, ['rsi_mean_reversion', 'bb_squeeze'], true)) {
            $this->executeSell($bot, $openTrade, $scan['idr_price'], 'signal');
            return;
        }

        if ($strategy === 'adaptive') {
            $regime = $scan['regime'] ?? '';
            if (str_contains($regime, 'sideways') || str_contains($regime, 'squeeze')) {
                $this->executeSell($bot, $openTrade, $scan['idr_price'], 'signal');
            }
        }
    }

    private function processPair(Bot $bot, string $binancePair): void
    {
        $scan    = $this->scanPair($bot, $binancePair);
        $signal  = $scan['signal'];

        if ($signal === 'hold' || $scan['error']) {
            return;
        }

        $idrPrice = $scan['idr_price'];
        if ($idrPrice <= 0) {
            $this->log($bot, 'warning', "Harga IDR tidak tersedia untuk {$binancePair}");
            return;
        }

        $openTrade = Trade::where('bot_id', $bot->id)
            ->where('binance_pair', $binancePair)
            ->where('status', 'open')
            ->where('mode', $bot->mode)
            ->first();

        if ($signal === 'buy' && ! $openTrade) {
            if ($this->isInCooldown($bot, $binancePair)) {
                return;
            }
            if ($scan['regime']) {
                $this->log($bot, 'info', "[Adaptive] {$binancePair} regime: {$scan['regime']}");
            }
            $this->executeBuy($bot, $binancePair, $scan['indodax_pair'], $idrPrice, $scan['indicators']);
        } elseif ($signal === 'sell' && $openTrade) {
            $strategy = $bot->settings?->strategy ?? 'ema_crossover';

            // Fee Indodax: 0.3% per sisi = 0.6% round trip
            // Signal sell hanya boleh dilakukan kalau profit bersih > fee + buffer (min 1.2%)
            $entryPrice     = (float) $openTrade->entry_price;
            $profitPct      = $entryPrice > 0 ? (($idrPrice - $entryPrice) / $entryPrice) * 100 : 0;
            $minProfitToSell = 1.2; // 0.6% fee + 0.6% buffer minimum

            // Mean reversion: signal sell aktif karena exit di BB adalah inti strategi
            // Tapi hanya kalau profit sudah cukup untuk menutup biaya admin
            if ($strategy === 'rsi_mean_reversion' && $profitPct >= $minProfitToSell) {
                $this->executeSell($bot, $openTrade, $idrPrice, 'signal');
            }

            // BB Squeeze: sama seperti mean reversion
            if ($strategy === 'bb_squeeze' && $profitPct >= $minProfitToSell) {
                $this->executeSell($bot, $openTrade, $idrPrice, 'signal');
            }

            // Adaptive: signal sell hanya untuk regime sideways/squeeze (bukan trending)
            if ($strategy === 'adaptive' && $profitPct >= $minProfitToSell) {
                $regime = $scan['regime'] ?? '';
                if (str_contains($regime, 'sideways') || str_contains($regime, 'squeeze')) {
                    $this->executeSell($bot, $openTrade, $idrPrice, 'signal');
                }
                // trending→EMA regime: biarkan SL/TP yang menangani
            }

            // EMA Crossover (trend following): tidak pakai signal sell, biarkan SL/TP
        }
    }

    // ─── Execution ────────────────────────────────────────────────────────────

    private function executeBuy(
        Bot    $bot,
        string $binancePair,
        string $indodaxPair,
        float  $idrPrice,
        array  $indicators
    ): void {
        $settings  = $bot->settings;
        $portfolio = $this->getPortfolioSnapshot($bot);

        // Cek slot posisi tersedia
        if ($portfolio['available_slots'] <= 0) {
            $this->log($bot, 'info',
                "Posisi penuh ({$portfolio['open_count']}/{$portfolio['max_positions']}). Skip BUY {$binancePair}."
            );
            return;
        }

        // Full balance allocation: modal dibagi rata sesuai max_positions.
        $tradeAmount = $portfolio['per_position_alloc'];

        // Pastikan modal bebas cukup
        $tradeAmount = min($tradeAmount, $portfolio['free_idr']);

        if ($tradeAmount < 10000) {
            $this->log($bot, 'warning',
                sprintf('Alokasi terlalu kecil: Rp %s (free: Rp %s)',
                    number_format($tradeAmount, 0, ',', '.'),
                    number_format($portfolio['free_idr'], 0, ',', '.')
                )
            );
            return;
        }

        $quantity = $tradeAmount / $idrPrice;
        $baseSlPct = (float) ($settings->stop_loss_percent ?? 3);
        $baseTpPct = (float) ($settings->take_profit_percent ?? 5);

        // Gunakan ATR untuk menyesuaikan lebar SL saat market volatil.
        // Tujuannya mengurangi false stop out tanpa membiarkan risk melebar tak terkendali.
        $atr = (float) ($indicators['atr'] ?? 0);
        $dynamicSlPct = $baseSlPct;

        if ($atr > 0 && ($indicators['current_price'] ?? 0) > 0) {
            $atrPct = ($atr / max($indicators['current_price'], 0.00000001)) * 100;
            $dynamicSlPct = max($baseSlPct, min($atrPct * 1.8, $baseSlPct * 1.75));
        }

        $dynamicTpPct = max($baseTpPct, $dynamicSlPct * 2.2);
        $slPrice = $idrPrice * (1 - $dynamicSlPct / 100);
        $tpPrice = $idrPrice * (1 + $dynamicTpPct / 100);

        $chandelierUsdt = (float) ($indicators['chandelier_long'] ?? 0);
        if ($chandelierUsdt > 0 && ($indicators['current_price'] ?? 0) > 0) {
            $usdtToIdr = $idrPrice / $indicators['current_price'];
            $chandelierIdr = $chandelierUsdt * $usdtToIdr;

            if ($chandelierIdr > 0 && $chandelierIdr < $idrPrice) {
                $slPrice = max($slPrice, $chandelierIdr);
            }
        }

        $exchangeOrderId = null;

        if (! $bot->isSimulation()) {
            $indodax = $this->makeIndodaxService($bot);
            $order   = $indodax->buy($indodaxPair, $tradeAmount, $idrPrice);
            $exchangeOrderId = $order['return']['order_id'] ?? null;

            if (! $exchangeOrderId) {
                $this->log($bot, 'error', "Order BUY gagal di Indodax: {$indodaxPair}");
                return;
            }
        }

        $trade = Trade::create([
            'bot_id'            => $bot->id,
            'pair'              => $indodaxPair,
            'binance_pair'      => $binancePair,
            'type'              => 'buy',
            'mode'              => $bot->mode,
            'status'            => 'open',
            'entry_price'       => $idrPrice,
            'quantity'          => $quantity,
            'amount_idr'        => $tradeAmount,
            'stop_loss_price'   => $slPrice,
            'take_profit_price' => $tpPrice,
            'signal'            => 'buy',
            'strategy'          => $bot->settings?->strategy ?? 'ema_crossover',
            'peak_price'        => $idrPrice,
            'indicators'        => $indicators,
            'exchange_order_id' => $exchangeOrderId,
        ]);

        $this->deductBalance($bot, 'IDR', $tradeAmount);
        $this->addBalance($bot, strtoupper(explode('_', $indodaxPair)[0]), $quantity);

        $allocPct = $portfolio['total_capital'] > 0
            ? round(($tradeAmount / $portfolio['total_capital']) * 100, 1)
            : 0;

        $simLabel = $bot->isSimulation() ? '[SIMULASI] ' : '';
        $this->log($bot, 'info',
            sprintf('%sBUY %s @ Rp %s | Alokasi: Rp %s (%s%% modal) | SL: Rp %s | TP: Rp %s | Slot %d/%d',
                $simLabel,
                $this->binance->getDisplayName($binancePair),
                number_format($idrPrice, 0, ',', '.'),
                number_format($tradeAmount, 0, ',', '.'),
                $allocPct,
                number_format($slPrice, 0, ',', '.'),
                number_format($tpPrice, 0, ',', '.'),
                $portfolio['open_count'] + 1,
                $portfolio['max_positions']
            ),
            ['trade_id' => $trade->id]
        );
    }

    private function executeSell(Bot $bot, Trade $trade, float $currentIdrPrice, string $reason): void
    {
        $indodaxPair     = $trade->pair;
        $exchangeOrderId = null;

        if (! $bot->isSimulation()) {
            $indodax = $this->makeIndodaxService($bot);

            // Cek saldo aktual sebelum sell
            $balances       = $indodax->getBalance();
            $cryptoKey      = strtolower(explode('_', $indodaxPair)[0]);
            $actualBalance  = (float) ($balances[$cryptoKey] ?? 0);
            $wantToSell     = (float) $trade->quantity;

            if ($actualBalance <= 0) {
                $this->log($bot, 'error', "SELL dibatalkan: saldo {$cryptoKey} di Indodax = 0");
                return;
            }

            // Pakai saldo aktual jika lebih kecil dari qty trade (misal sudah terjual sebagian)
            $sellQty = min($wantToSell, $actualBalance);

            $order           = $indodax->sell($indodaxPair, $sellQty, $currentIdrPrice);
            $exchangeOrderId = $order['return']['order_id'] ?? null;

            if (! $exchangeOrderId) {
                $errMsg = $order['error'] ?? $order['error_code'] ?? 'unknown error';
                $this->log($bot, 'error', "Order SELL gagal di Indodax: {$indodaxPair} — {$errMsg}");
                return;
            }
        }

        $saleIdr       = (float) $trade->quantity * $currentIdrPrice;
        $profitLoss    = $saleIdr - (float) $trade->amount_idr;
        $profitLossPct = (float) $trade->entry_price > 0
            ? (($currentIdrPrice - (float) $trade->entry_price) / (float) $trade->entry_price) * 100
            : 0;

        $trade->update([
            'status'              => 'closed',
            'exit_price'          => $currentIdrPrice,
            'profit_loss'         => $profitLoss,
            'profit_loss_percent' => $profitLossPct,
            'close_reason'        => $reason,
            'closed_at'           => Carbon::now(),
        ]);

        $this->addBalance($bot, 'IDR', $saleIdr);
        $this->deductBalance($bot, strtoupper(explode('_', $indodaxPair)[0]), (float) $trade->quantity);

        $plSign   = $profitLoss >= 0 ? '+' : '';
        $simLabel = $bot->isSimulation() ? '[SIMULASI] ' : '';
        $this->log($bot, $profitLoss >= 0 ? 'info' : 'warning',
            sprintf('%sSELL %s @ Rp %s | P/L: %sRp %s (%s%.2f%%) | %s',
                $simLabel,
                $this->binance->getDisplayName($trade->binance_pair),
                number_format($currentIdrPrice, 0, ',', '.'),
                $plSign,
                number_format(abs($profitLoss), 0, ',', '.'),
                $plSign,
                $profitLossPct,
                strtoupper(str_replace('_', ' ', $reason))
            ),
            ['trade_id' => $trade->id]
        );
    }

    // ─── Position Monitoring ──────────────────────────────────────────────────

    public function monitorPositions(Bot $bot): void
    {
        $openTrades = Trade::where('bot_id', $bot->id)->where('status', 'open')->where('mode', $bot->mode)->get();

        if ($openTrades->isEmpty()) {
            return;
        }

        $trailingEnabled = (bool) ($bot->settings?->trailing_sl_enabled ?? false);
        $trailingPct     = (float) ($bot->settings?->trailing_sl_percent ?? 1.5);

        foreach ($openTrades as $trade) {
            $currentPrice = $this->binance->getCurrentIdrPrice($trade->binance_pair);

            if ($currentPrice <= 0) {
                continue;
            }

            $this->refreshChandelierStop($bot, $trade, $currentPrice);

            // ── Trailing Stop Loss ────────────────────────────────────────────
            if ($trailingEnabled && $currentPrice > $trade->entry_price) {
                $peakPrice = (float) ($trade->peak_price ?? $trade->entry_price);

                if ($currentPrice > $peakPrice) {
                    $peakPrice = $currentPrice;
                    $newSl     = $peakPrice * (1 - $trailingPct / 100);

                    if ($newSl > (float) $trade->stop_loss_price) {
                        $trade->update([
                            'peak_price'      => $peakPrice,
                            'stop_loss_price' => $newSl,
                        ]);
                        $this->log($bot, 'info', sprintf(
                            'Trailing SL %s diperbarui → Rp %s (peak: Rp %s)',
                            $trade->binance_pair,
                            number_format($newSl, 0, ',', '.'),
                            number_format($peakPrice, 0, ',', '.')
                        ));
                    }
                }
            }

            // ── Exit check berdasarkan stop_loss_price & take_profit_price ───
            $reason = $this->strategy->checkExitCondition(
                (float) $trade->entry_price,
                $currentPrice,
                (float) $bot->settings->stop_loss_percent,
                (float) $bot->settings->take_profit_percent,
                (float) $trade->stop_loss_price,
                (float) $trade->take_profit_price
            );

            if ($reason) {
                $this->executeSell($bot, $trade, $currentPrice, $reason);
            }
        }
    }

    private function refreshChandelierStop(Bot $bot, Trade $trade, float $currentPrice): void
    {
        $settings = $bot->settings;

        if (! $settings || $currentPrice <= 0) {
            return;
        }

        $klines = $this->binance->getKlines(
            $trade->binance_pair,
            $settings->kline_interval ?? '15m',
            $this->resolveKlineLimit($settings)
        );

        if (empty($klines)) {
            return;
        }

        $indicators = $this->indicator->calculate(
            $klines,
            $settings->ema_fast ?? 20,
            $settings->ema_slow ?? 50,
            $settings->rsi_period ?? 14,
            $settings->bb_period ?? 20
        );

        $chandelierUsdt = (float) ($indicators['chandelier_long'] ?? 0);
        $indicatorPrice = (float) ($indicators['current_price'] ?? 0);

        if ($chandelierUsdt <= 0 || $indicatorPrice <= 0) {
            return;
        }

        $chandelierIdr = $chandelierUsdt * ($currentPrice / $indicatorPrice);

        if ($chandelierIdr > (float) $trade->stop_loss_price && $chandelierIdr < $currentPrice) {
            $trade->update(['stop_loss_price' => $chandelierIdr]);
            $this->log($bot, 'info', sprintf(
                'Chandelier SL %s diperbarui → Rp %s',
                $trade->binance_pair,
                number_format($chandelierIdr, 0, ',', '.')
            ));
        }
    }

    // ─── Balance Helpers ──────────────────────────────────────────────────────

    /**
     * Close all open positions at current market price.
     * Returns number of positions closed.
     */
    public function closeAllPositions(Bot $bot, ?Trade $singleTrade = null): int
    {
        $trades = $singleTrade
            ? collect([$singleTrade])
            : Trade::where('bot_id', $bot->id)->where('status', 'open')->get();

        $count = 0;

        foreach ($trades as $trade) {
            try {
                $currentPrice = $this->binance->getCurrentIdrPrice($trade->binance_pair);
                if ($currentPrice <= 0) {
                    $currentPrice = (float) $trade->entry_price;
                }
                $this->executeSell($bot, $trade, $currentPrice, 'manual');
                $count++;
            } catch (\Throwable $e) {
                Log::warning("closeAllPositions: gagal close {$trade->binance_pair} — {$e->getMessage()}");
            }
        }

        return $count;
    }

    public function getAvailableIdr(Bot $bot): float
    {
        if ($bot->isSimulation()) {
            return (float) Balance::where('bot_id', $bot->id)
                ->where('mode', 'simulation')
                ->where('currency', 'IDR')
                ->value('amount') ?? 0.0;
        }

        $balances = $this->makeIndodaxService($bot)->getBalance();
        return (float) ($balances['idr'] ?? 0);
    }

    private function addBalance(Bot $bot, string $currency, float $amount): void
    {
        if (! $bot->isSimulation()) return;

        Balance::where('bot_id', $bot->id)
            ->where('mode', 'simulation')
            ->where('currency', strtoupper($currency))
            ->update(['amount' => DB::raw("amount + {$amount}")]);
    }

    private function deductBalance(Bot $bot, string $currency, float $amount): void
    {
        if (! $bot->isSimulation()) return;

        Balance::where('bot_id', $bot->id)
            ->where('mode', 'simulation')
            ->where('currency', strtoupper($currency))
            ->update(['amount' => DB::raw("GREATEST(0, amount - {$amount})")]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Cek apakah pair sedang dalam cooldown setelah stop loss.
     * Cooldown = cooldown_candles × durasi interval (dalam menit).
     */
    private function isInCooldown(Bot $bot, string $binancePair): bool
    {
        $cooldownCandles = (int) ($bot->settings?->cooldown_candles ?? 3);

        if ($cooldownCandles <= 0) {
            return false;
        }

        $intervalMinutes = match ($bot->settings?->kline_interval ?? '15m') {
            '1m'  => 1,
            '3m'  => 3,
            '5m'  => 5,
            '15m' => 15,
            '30m' => 30,
            '1h'  => 60,
            default => 15,
        };

        $cooldownMinutes = $cooldownCandles * $intervalMinutes;

        $lastSl = Trade::where('bot_id', $bot->id)
            ->where('binance_pair', $binancePair)
            ->where('status', 'closed')
            ->where('mode', $bot->mode)
            ->where('close_reason', 'stop_loss')
            ->orderByDesc('closed_at')
            ->first();

        if (! $lastSl) {
            return false;
        }

        $inCooldown = $lastSl->closed_at->diffInMinutes(now()) < $cooldownMinutes;

        if ($inCooldown) {
            $remaining = $cooldownMinutes - $lastSl->closed_at->diffInMinutes(now());
            $this->log($bot, 'info', sprintf(
                'Cooldown aktif untuk %s — tunggu %d menit lagi (SL %s)',
                $binancePair,
                $remaining,
                $lastSl->closed_at->format('H:i')
            ));
        }

        return $inCooldown;
    }

    /**
     * Auto-hitung kline limit berdasarkan strategi dan interval.
     * Warmup = periode terpanjang × 2 (untuk indikator stabil).
     * Minimal 150, maksimal 300 (batas API Gate.io).
     */
    private function resolveKlineLimit($settings): int
    {
        $strategy  = $settings?->strategy ?? 'ema_crossover';
        $emaSlow   = (int) ($settings?->ema_slow  ?? 50);
        $bbPeriod  = (int) ($settings?->bb_period ?? 20);
        $rsiPeriod = (int) ($settings?->rsi_period ?? 14);

        $warmup = match ($strategy) {
            'ema_crossover'      => max($emaSlow, $rsiPeriod) * 2 + 30,
            'rsi_mean_reversion' => max($bbPeriod, $rsiPeriod) * 3 + 30,
            'bb_squeeze'         => max($bbPeriod, $rsiPeriod) * 3 + 50,
            default              => 150,
        };

        return max(150, min($warmup, 300));
    }

    /**
     * Cek apakah total kerugian hari ini sudah melewati batas max_daily_loss_percent.
     */
    private function isMaxDailyLossReached(Bot $bot, float $totalCapital): bool
    {
        $maxLossPct = (float) ($bot->settings?->max_daily_loss_percent ?? 5);

        if ($maxLossPct <= 0 || $totalCapital <= 0) {
            return false;
        }

        $todayLoss = (float) Trade::where('bot_id', $bot->id)
            ->where('status', 'closed')
            ->where('mode', $bot->mode)
            ->whereDate('closed_at', today())
            ->where('profit_loss', '<', 0)
            ->sum('profit_loss');

        $maxLossIdr = $totalCapital * ($maxLossPct / 100);

        return abs($todayLoss) >= $maxLossIdr;
    }

    private function makeIndodaxService(Bot $bot): IndodaxService
    {
        return new IndodaxService($bot->indodax_api_key ?? '', $bot->indodax_api_secret ?? '');
    }

    private function log(Bot $bot, string $level, string $message, array $context = []): void
    {
        BotLog::create([
            'bot_id'  => $bot->id,
            'level'   => $level,
            'message' => $message,
            'context' => $context ?: null,
        ]);
    }
}
