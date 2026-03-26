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

        $this->monitorPositions($bot);

        foreach ($bot->settings->pairs as $binancePair) {
            $this->processPair($bot, $binancePair);
        }

        $this->log($bot, 'info', 'Bot run selesai.');
    }

    // ─── Portfolio Snapshot ───────────────────────────────────────────────────

    /**
     * Snapshot kondisi portofolio saat ini.
     *
     * Konsep Full Balance System:
     *   total_capital = free_idr + nilai IDR semua posisi terbuka
     *   per_position  = total_capital / max_positions
     *   → setiap trade mendapat alokasi yang sama dari total modal
     */
    public function getPortfolioSnapshot(Bot $bot): array
    {
        $settings     = $bot->settings;
        $maxPositions = (int) ($settings?->max_positions ?? 4);

        $freeIdr = $this->getAvailableIdr($bot);

        // Nilai modal yang sedang "dipakai" di posisi terbuka
        $lockedIdr = (float) Trade::where('bot_id', $bot->id)
            ->where('status', 'open')
            ->sum('amount_idr');

        $totalCapital    = $freeIdr + $lockedIdr;
        $perPositionAlloc = $maxPositions > 0 ? $totalCapital / $maxPositions : $totalCapital;
        $openCount        = Trade::where('bot_id', $bot->id)->where('status', 'open')->count();
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
            'indicators'   => [],
            'idr_price'    => 0,
            'error'        => null,
        ];

        $klines = $this->binance->getKlines(
            $binancePair,
            $settings->kline_interval ?? '5m',
            $settings->kline_limit    ?? 100
        );

        if (empty($klines)) {
            $base['error'] = 'Gagal mengambil data kline dari Binance. Periksa koneksi internet.';
            return $base;
        }

        $indicators = $this->indicator->calculate(
            $klines,
            $settings->ema_fast   ?? 20,
            $settings->ema_slow   ?? 50,
            $settings->rsi_period ?? 14
        );

        $idrPrice = $this->binance->getIdrPrice($binancePair);
        if ($idrPrice <= 0) {
            $idrPrice = round(($indicators['current_price'] ?? 0) * 16000);
        }

        $signal = $this->strategy->analyze($indicators);

        return array_merge($base, [
            'signal'     => $signal,
            'indicators' => $indicators,
            'idr_price'  => $idrPrice,
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
            ->first();

        if ($signal === 'buy' && ! $openTrade) {
            $this->executeBuy($bot, $binancePair, $scan['indodax_pair'], $idrPrice, $scan['indicators']);
        } elseif ($signal === 'sell' && $openTrade) {
            $this->executeSell($bot, $openTrade, $idrPrice, 'signal');
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

        // ── Full Balance Allocation ───────────────────────────────────────────
        // Setiap posisi mendapat: total_capital / max_positions
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
        $slPct    = (float) ($settings->stop_loss_percent ?? 3);
        $tpPct    = (float) ($settings->take_profit_percent ?? 5);
        $slPrice  = $idrPrice * (1 - $slPct / 100);
        $tpPrice  = $idrPrice * (1 + $tpPct / 100);

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
            $order   = $indodax->sell($indodaxPair, (float) $trade->quantity, $currentIdrPrice);
            $exchangeOrderId = $order['return']['order_id'] ?? null;

            if (! $exchangeOrderId) {
                $this->log($bot, 'error', "Order SELL gagal di Indodax: {$indodaxPair}");
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
        $openTrades = Trade::where('bot_id', $bot->id)->where('status', 'open')->get();

        if ($openTrades->isEmpty()) {
            return;
        }

        foreach ($openTrades as $trade) {
            $currentPrice = $this->binance->getCurrentIdrPrice($trade->binance_pair);

            if ($currentPrice <= 0) {
                continue;
            }

            $reason = $this->strategy->checkExitCondition(
                (float) $trade->entry_price,
                $currentPrice,
                (float) $bot->settings->stop_loss_percent,
                (float) $bot->settings->take_profit_percent
            );

            if ($reason) {
                $this->executeSell($bot, $trade, $currentPrice, $reason);
            }
        }
    }

    // ─── Balance Helpers ──────────────────────────────────────────────────────

    /**
     * Close all open positions at current market price.
     * Returns number of positions closed.
     */
    public function closeAllPositions(Bot $bot): int
    {
        $trades = Trade::where('bot_id', $bot->id)->where('status', 'open')->get();
        $count  = 0;

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
