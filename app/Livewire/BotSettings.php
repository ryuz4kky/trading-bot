<?php

namespace App\Livewire;

use App\Models\Balance;
use App\Models\Bot;
use App\Models\BotLog;
use App\Models\BotSetting;
use App\Models\Trade;
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
        }
    }

    public function saveSettings(): void
    {
        $this->resetMessages();

        $this->validate([
            'botName'            => 'required|string|max:100',
            'mode'               => 'required|in:simulation,real',
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
            ]
        );

        $this->successMessage = 'Settings berhasil disimpan.';
        $this->bot = Bot::with('settings')->find($this->bot->id);
        $this->syncFromModel();
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
            $indodax  = new IndodaxService($this->bot->indodax_api_key, $this->bot->indodax_api_secret);
            $balances = $indodax->getBalance();

            if (empty($balances)) {
                $this->syncError = 'Gagal mengambil saldo. Periksa API Key atau koneksi internet.';
                return;
            }

            $this->indodaxBalances = $balances;

            // Set IDR balance ke bot
            $idrAmount = (float) ($balances['idr'] ?? 0);

            if ($idrAmount > 0) {
                if ($this->bot->isSimulation()) {
                    // Update simulation balance
                    $this->bot->update(['simulation_balance' => $idrAmount]);
                    Balance::updateOrCreate(
                        ['bot_id' => $this->bot->id, 'mode' => 'simulation', 'currency' => 'IDR'],
                        ['amount' => $idrAmount, 'locked' => 0]
                    );
                } else {
                    // Update real balance
                    Balance::updateOrCreate(
                        ['bot_id' => $this->bot->id, 'mode' => 'real', 'currency' => 'IDR'],
                        ['amount' => $idrAmount, 'locked' => 0]
                    );
                }
            }

            $this->syncSuccess = true;
            $this->bot = Bot::with('settings')->find($this->bot->id);
            $this->syncFromModel();

        } catch (\Throwable $e) {
            $this->syncError = 'Error: ' . $e->getMessage();
        }
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
