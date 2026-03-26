<?php

namespace App\Livewire;

use App\Models\Bot;
use App\Models\BotLog;
use App\Models\Trade;
use App\Services\BotService;
use Livewire\Component;

class Dashboard extends Component
{
    public Bot $bot;

    // Displayed state (refreshed via wire:poll)
    public string $botStatus   = 'stopped';
    public string $botMode     = 'simulation';
    public float  $idrBalance  = 0;
    public float  $todayProfit = 0;
    public int    $openCount   = 0;
    public array  $openTrades  = [];
    public array  $recentTrades = [];
    public array  $recentLogs  = [];

    // Portfolio snapshot
    public float $totalCapital    = 0;
    public float $lockedIdr       = 0;
    public int   $maxPositions    = 4;
    public float $utilizationPct  = 0;
    public float $perPositionAlloc = 0;

    // Scan state
    public bool   $scanning     = false;
    public array  $scanResults  = [];
    public string $scanError    = '';
    public string $successMsg   = '';

    // Log management
    public array $selectedLogIds = [];

    public function mount(): void
    {
        $this->bot = Bot::with(['settings', 'balances'])->firstOrFail();
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->bot = Bot::with(['settings', 'balances'])->find($this->bot->id);

        $this->botStatus  = $this->bot->status;
        $this->botMode    = $this->bot->mode;

        $this->todayProfit = (float) Trade::where('bot_id', $this->bot->id)
            ->where('status', 'closed')
            ->whereDate('closed_at', today())
            ->sum('profit_loss');

        $this->openTrades = Trade::where('bot_id', $this->bot->id)
            ->where('status', 'open')
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
        $this->openCount = count($this->openTrades);

        $this->recentTrades = Trade::where('bot_id', $this->bot->id)
            ->where('status', 'closed')
            ->orderByDesc('closed_at')
            ->limit(20)
            ->get()
            ->toArray();

        $this->recentLogs = BotLog::where('bot_id', $this->bot->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->toArray();

        // Portfolio snapshot
        $botService           = app(\App\Services\BotService::class);
        $portfolio            = $botService->getPortfolioSnapshot($this->bot);
        $this->totalCapital   = $portfolio['total_capital'];
        $this->lockedIdr      = $portfolio['locked_idr'];
        $this->maxPositions   = $portfolio['max_positions'];
        $this->utilizationPct = $portfolio['utilization_pct'];
        $this->perPositionAlloc = $portfolio['per_position_alloc'];

        // IDR Balance
        if ($this->bot->isSimulation()) {
            $balance = $this->bot->balances()
                ->where('mode', 'simulation')
                ->where('currency', 'IDR')
                ->first();
            $this->idrBalance = $balance ? (float) $balance->amount : 0;
        }
    }

    public function startBot(): void
    {
        $this->bot->update(['status' => 'running']);
        $this->successMsg = 'Bot berhasil dijalankan.';
        $this->refresh();
    }

    public function stopBot(): void
    {
        $this->bot->update(['status' => 'stopped']);
        $this->successMsg = 'Bot dihentikan.';
        $this->refresh();
    }

    public function scanMarket(): void
    {
        $this->scanning    = true;
        $this->scanResults = [];
        $this->scanError   = '';
        $this->successMsg  = '';

        try {
            $service = app(BotService::class);
            $this->scanResults = $service->scanAll($this->bot);

            if (empty($this->scanResults)) {
                $this->scanError = 'Tidak ada data. Pastikan pair aktif sudah dikonfigurasi.';
            }
        } catch (\Throwable $e) {
            $this->scanError = 'Scan gagal: ' . $e->getMessage();
        } finally {
            $this->scanning = false;
        }

        $this->refresh();
    }

    public function closeAllPositions(): void
    {
        $service = app(BotService::class);
        $count   = $service->closeAllPositions($this->bot);

        $this->successMsg = $count > 0
            ? "{$count} posisi berhasil dijual."
            : 'Tidak ada posisi terbuka.';

        $this->refresh();
    }

    public function deleteAllLogs(): void
    {
        BotLog::where('bot_id', $this->bot->id)->delete();
        $this->selectedLogIds = [];
        $this->recentLogs     = [];
    }

    public function deleteSelectedLogs(): void
    {
        if (empty($this->selectedLogIds)) {
            return;
        }

        BotLog::where('bot_id', $this->bot->id)
            ->whereIn('id', $this->selectedLogIds)
            ->delete();

        $this->selectedLogIds = [];
        $this->refresh();
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
