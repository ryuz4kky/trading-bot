<?php

namespace App\Livewire;

use App\Models\Balance;
use App\Models\Bot;
use App\Models\BotLog;
use App\Models\BotSetting;
use App\Models\Trade;
use App\Services\BinanceService;
use App\Services\IndodaxService;
use Livewire\Component;

class BotSettings extends Component
{
    public Bot $bot;

    // General
    public string $botName  = '';
    public string $mode     = 'simulation';

    // Trading params
    public array  $selectedPairs     = [];
    public string $riskPercent       = '2';
    public string $stopLossPercent   = '3';
    public string $takeProfitPercent = '5';
    public string $emaFast           = '20';
    public string $emaSlow           = '50';
    public string $rsiPeriod         = '14';
    public string $klineInterval     = '5m';
    public string $maxPositions      = '4';
    public string $simulationBalance = '10000000';
    public string $strategy             = 'ema_crossover';
    public string $bbPeriod             = '20';
    public string $maxDailyLossPercent  = '5';
    public bool   $trailingSlEnabled    = false;
    public string $trailingSlPercent    = '1.5';
    public string $cooldownCandles      = '3';
    public string $volumeMinRatio       = '1.2';

    // API key form
    public bool   $showApiForm  = false;
    public string $newApiKey    = '';
    public string $newApiSecret = '';

    // Sync saldo
    public array  $indodaxBalances = [];
    public bool   $syncSuccess     = false;
    public string $syncError       = '';

    // Feedback
    public string $successMessage = '';
    public string $errorMessage   = '';

    public function mount(): void
    {
        $this->bot = Bot::with('settings')->firstOrFail();
        $this->syncFromModel();
    }

    private function syncFromModel(): void
    {
        $s = $this->bot->settings;

        $this->botName           = $this->bot->name;
        $this->mode              = $this->bot->mode;
        $this->simulationBalance = (string) (int) $this->bot->simulation_balance;

        if ($s) {
            $this->selectedPairs     = $s->pairs ?? [];
            $this->riskPercent       = (string) (float) $s->risk_percent;
            $this->stopLossPercent   = (string) (float) $s->stop_loss_percent;
            $this->takeProfitPercent = (string) (float) $s->take_profit_percent;
            $this->emaFast           = (string) $s->ema_fast;
            $this->emaSlow           = (string) $s->ema_slow;
            $this->rsiPeriod         = (string) $s->rsi_period;
            $this->klineInterval     = $s->kline_interval;
            $this->maxPositions      = (string) ($s->max_positions ?? 4);
            $this->strategy          = $s->strategy ?? 'ema_crossover';
            $this->bbPeriod             = (string) ($s->bb_period ?? 20);
            $this->maxDailyLossPercent  = (string) (float) ($s->max_daily_loss_percent ?? 5);
            $this->trailingSlEnabled    = (bool) ($s->trailing_sl_enabled ?? false);
            $this->trailingSlPercent    = (string) (float) ($s->trailing_sl_percent ?? 1.5);
            $this->cooldownCandles      = (string) ($s->cooldown_candles ?? 3);
            $this->volumeMinRatio       = (string) (float) ($s->volume_min_ratio ?? 1.2);
        }
    }

    public function saveSettings(): void
    {
        $this->resetMessages();

        $this->validate([
            'botName'            => 'required|string|max:100',
            'mode'               => 'required|in:simulation,real',
            'strategy'           => 'required|in:ema_crossover,rsi_mean_reversion,bb_squeeze',
            'bbPeriod'              => 'required|integer|min:5|max:50',
            'maxDailyLossPercent'   => 'required|numeric|min:1|max:50',
            'trailingSlPercent'     => 'required_if:trailingSlEnabled,true|numeric|min:0.1|max:10',
            'cooldownCandles'       => 'required|integer|min:0|max:20',
            'volumeMinRatio'        => 'required|numeric|min:0.5|max:5',
            'riskPercent'        => 'required|numeric|min:0.1|max:10',
            'stopLossPercent'    => 'required|numeric|min:0.1|max:20',
            'takeProfitPercent'  => 'required|numeric|min:0.1|max:50',
            'emaFast'            => 'required|integer|min:2|max:100',
            'emaSlow'            => 'required|integer|min:5|max:200',
            'rsiPeriod'          => 'required|integer|min:5|max:50',
            'maxPositions'       => 'required|integer|min:1|max:10',
            'selectedPairs'      => 'required|array|min:1',
            'simulationBalance'  => 'required|numeric|min:10000',
        ], [
            'selectedPairs.required' => 'Pilih minimal 1 pair.',
            'selectedPairs.min'      => 'Pilih minimal 1 pair.',
        ]);

        $pairs = collect($this->selectedPairs)
            ->map(fn($p) => strtoupper(trim($p)))
            ->filter()
            ->values()
            ->toArray();

        $this->bot->update([
            'name'               => $this->botName,
            'mode'               => $this->mode,
            'simulation_balance' => (int) $this->simulationBalance,
        ]);

        BotSetting::updateOrCreate(
            ['bot_id' => $this->bot->id],
            [
                'pairs'               => $pairs,
                'risk_percent'        => (float) $this->riskPercent,
                'stop_loss_percent'   => (float) $this->stopLossPercent,
                'take_profit_percent' => (float) $this->takeProfitPercent,
                'ema_fast'            => (int) $this->emaFast,
                'ema_slow'            => (int) $this->emaSlow,
                'rsi_period'          => (int) $this->rsiPeriod,
                'kline_interval'      => $this->klineInterval,
                'max_positions'       => (int) $this->maxPositions,
                'strategy'                => $this->strategy,
                'bb_period'               => (int) $this->bbPeriod,
                'max_daily_loss_percent'  => (float) $this->maxDailyLossPercent,
                'trailing_sl_enabled'     => $this->trailingSlEnabled,
                'trailing_sl_percent'     => (float) $this->trailingSlPercent,
                'cooldown_candles'        => (int) $this->cooldownCandles,
                'volume_min_ratio'        => (float) $this->volumeMinRatio,
            ]
        );

        $this->successMessage = 'Settings berhasil disimpan.';
        $this->bot = Bot::with('settings')->find($this->bot->id);
        $this->syncFromModel();
    }

    public function applyRecommended(): void
    {
        match ($this->strategy) {
            'rsi_mean_reversion' => $this->applyValues(rsi: 14, bb: 20, interval: '15m', sl: 3, tp: 6),
            'bb_squeeze'         => $this->applyValues(rsi: 14, bb: 20, interval: '1h',  sl: 4, tp: 8),
            default              => $this->applyValues(emaFast: 20, emaSlow: 50, rsi: 14, interval: '15m', sl: 3, tp: 6),
        };
    }

    private function applyValues(
        int    $emaFast  = 20,
        int    $emaSlow  = 50,
        int    $rsi      = 14,
        int    $bb       = 20,
        string $interval = '15m',
        float  $sl       = 3,
        float  $tp       = 6,
    ): void {
        $this->emaFast           = (string) $emaFast;
        $this->emaSlow           = (string) $emaSlow;
        $this->rsiPeriod         = (string) $rsi;
        $this->bbPeriod          = (string) $bb;
        $this->klineInterval     = $interval;
        $this->stopLossPercent   = (string) $sl;
        $this->takeProfitPercent = (string) $tp;
    }

    public function selectAllPairs(): void
    {
        $this->selectedPairs = array_keys(\App\Services\BinanceService::availablePairs());
    }

    public function clearAllPairs(): void
    {
        $this->selectedPairs = [];
    }

    public function saveApiKeys(): void
    {
        $this->resetMessages();

        $this->validate([
            'newApiKey'    => 'required|string|min:10|max:255',
            'newApiSecret' => 'required|string|min:10|max:255',
        ], [
            'newApiKey.required'    => 'API Key wajib diisi.',
            'newApiKey.min'         => 'API Key minimal 10 karakter.',
            'newApiSecret.required' => 'Secret Key wajib diisi.',
            'newApiSecret.min'      => 'Secret Key minimal 10 karakter.',
        ]);

        // Lewat setAttribute agar mutator enkripsi berjalan
        $this->bot->setAttribute('indodax_api_key',    $this->newApiKey);
        $this->bot->setAttribute('indodax_api_secret', $this->newApiSecret);
        $this->bot->save();

        $this->newApiKey    = '';
        $this->newApiSecret = '';
        $this->showApiForm  = false;
        $this->successMessage = 'API Key berhasil disimpan dan dienkripsi.';
        $this->bot->refresh();
    }

    public function resetSimulationBalance(): void
    {
        $this->resetMessages();

        $amount = (int) $this->simulationBalance;

        Balance::updateOrCreate(
            ['bot_id' => $this->bot->id, 'mode' => 'simulation', 'currency' => 'IDR'],
            ['amount' => $amount, 'locked' => 0]
        );

        foreach (['BTC', 'ETH', 'BNB', 'SOL', 'XRP'] as $currency) {
            Balance::where('bot_id', $this->bot->id)
                ->where('mode', 'simulation')
                ->where('currency', $currency)
                ->update(['amount' => 0, 'locked' => 0]);
        }

        $this->successMessage = 'Saldo simulasi direset ke Rp ' . number_format($amount, 0, ',', '.') . '.';
    }

    public function syncBalanceFromIndodax(): void
    {
        $this->syncSuccess     = false;
        $this->syncError       = '';
        $this->indodaxBalances = [];

        $this->bot = Bot::with('settings')->find($this->bot->id);

        if (! $this->bot->hasApiKeys()) {
            $this->syncError = 'API Key belum dikonfigurasi.';
            return;
        }

        try {
            $indodax = new IndodaxService($this->bot->indodax_api_key, $this->bot->indodax_api_secret);
            $info    = $indodax->getFullBalanceInfo();

            $balance     = $info['balance'];
            $balanceHold = $info['balance_hold'];

            if (empty($balance)) {
                $this->syncError = 'Gagal mengambil saldo. Periksa API Key atau koneksi internet.';
                return;
            }

            $this->indodaxBalances = $balance;

            $mode = $this->bot->isSimulation() ? 'simulation' : 'real';

            // Simpan semua currency dari Indodax
            // locked diambil dari balance_hold API, tapi kalau 0 tetap di-set 0
            // (balance_hold Indodax kadang stale meski tidak ada open order)
            foreach ($balance as $currency => $amount) {
                $amount = (float) $amount;
                $locked = (float) ($balanceHold[$currency] ?? 0);

                if ($amount <= 0 && $locked <= 0) {
                    continue;
                }

                Balance::updateOrCreate(
                    ['bot_id' => $this->bot->id, 'mode' => $mode, 'currency' => strtoupper($currency)],
                    ['amount' => $amount, 'locked' => $locked]
                );
            }

            // Hapus hold di DB untuk currency yang tidak ada di balance_hold Indodax
            // (samakan dengan kondisi real exchange)
            Balance::where('bot_id', $this->bot->id)
                ->where('mode', $mode)
                ->where('locked', '>', 0)
                ->get()
                ->each(function ($b) use ($balanceHold) {
                    $currency = strtolower($b->currency);
                    if (! isset($balanceHold[$currency]) || (float) $balanceHold[$currency] <= 0) {
                        $b->update(['locked' => 0]);
                    }
                });

            // Update simulation_balance dari IDR jika mode simulasi
            if ($this->bot->isSimulation()) {
                $idrAmount = (float) ($balance['idr'] ?? 0);
                if ($idrAmount > 0) {
                    $this->bot->update(['simulation_balance' => $idrAmount]);
                }
            }

            $this->syncSuccess = true;
            $this->bot = Bot::with('settings')->find($this->bot->id);
            $this->syncFromModel();

        } catch (\Throwable $e) {
            $this->syncError = 'Error: ' . $e->getMessage();
        }
    }

    public function importHoldingsAsTrades(): void
    {
        $this->syncError   = '';
        $this->syncSuccess = false;

        $mode     = $this->bot->isSimulation() ? 'simulation' : 'real';
        $settings = $this->bot->settings;
        $binance  = app(BinanceService::class);

        // Reverse map: currency (uppercase) -> binance pair
        // e.g. BTC -> BTCUSDT
        $holdings = Balance::where('bot_id', $this->bot->id)
            ->where('mode', $mode)
            ->where('currency', '!=', 'IDR')
            ->get();

        if ($holdings->isEmpty()) {
            $this->syncError = 'Tidak ada crypto holdings yang tersimpan. Sync saldo dulu.';
            return;
        }

        $imported     = 0;
        $skipReasons  = [];

        foreach ($holdings as $holding) {
            $currency    = strtolower($holding->currency);
            $binancePair = strtoupper($currency) . 'USDT';
            $indodaxPair = $currency . '_idr';
            // Gunakan total amount — balance_hold Indodax kadang non-zero
            // meski tidak ada open order (dust hold internal)
            $available = (float) $holding->amount;

            if ($available <= 0) {
                $skipReasons[] = strtoupper($currency) . ': jumlah 0';
                continue;
            }

            // Ambil harga IDR saat ini
            $entryPrice = $binance->getCurrentIdrPrice($binancePair);

            if ($entryPrice <= 0) {
                $skipReasons[] = strtoupper($currency) . ': harga IDR tidak ditemukan (pair tidak didukung)';
                continue;
            }

            $amountIdr       = $available * $entryPrice;
            $slPercent       = (float) ($settings?->stop_loss_percent ?? 3);
            $tpPercent       = (float) ($settings?->take_profit_percent ?? 6);
            $stopLossPrice   = $entryPrice * (1 - $slPercent / 100);
            $takeProfitPrice = $entryPrice * (1 + $tpPercent / 100);

            $existingTrade = Trade::where('bot_id', $this->bot->id)
                ->where('pair', $indodaxPair)
                ->where('status', 'open')
                ->first();

            if ($existingTrade) {
                $existingTrade->update([
                    'quantity'          => $available,
                    'amount_idr'        => $amountIdr,
                    'entry_price'       => $entryPrice,
                    'stop_loss_price'   => $stopLossPrice,
                    'take_profit_price' => $takeProfitPrice,
                ]);
            } else {
                Trade::create([
                    'bot_id'            => $this->bot->id,
                    'pair'              => $indodaxPair,
                    'binance_pair'      => $binancePair,
                    'type'              => 'buy',
                    'mode'              => $mode,
                    'status'            => 'open',
                    'entry_price'       => $entryPrice,
                    'quantity'          => $available,
                    'amount_idr'        => $amountIdr,
                    'stop_loss_price'   => $stopLossPrice,
                    'take_profit_price' => $takeProfitPrice,
                    'signal'            => 'buy',
                    'close_reason'      => null,
                ]);
            }

            $imported++;
        }

        if ($imported > 0) {
            $this->syncSuccess = true;
        }

        if (! empty($skipReasons)) {
            $this->syncError = 'Dilewati: ' . implode(' | ', $skipReasons);
        } elseif ($imported === 0) {
            $this->syncError = 'Tidak ada yang bisa diimport.';
        }

        $this->bot = Bot::with(['settings', 'balances'])->find($this->bot->id);
    }

    public function resetAllData(): void
    {
        Trade::where('bot_id', $this->bot->id)->delete();
        BotLog::where('bot_id', $this->bot->id)->delete();
        $this->successMessage = 'Semua trade dan log berhasil dihapus.';
    }

    public function toggleApiForm(): void
    {
        $this->showApiForm  = ! $this->showApiForm;
        $this->newApiKey    = '';
        $this->newApiSecret = '';
        $this->resetMessages();
    }

    private function resetMessages(): void
    {
        $this->successMessage = '';
        $this->errorMessage   = '';
    }

    public function render()
    {
        return view('livewire.bot-settings');
    }
}
