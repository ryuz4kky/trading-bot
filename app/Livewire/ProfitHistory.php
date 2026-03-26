<?php

namespace App\Livewire;

use App\Models\Bot;
use App\Models\Trade;
use Livewire\Component;
use Livewire\WithPagination;

class ProfitHistory extends Component
{
    use WithPagination;

    public Bot $bot;

    public string $filterPair   = '';
    public string $filterResult = ''; // 'profit' | 'loss' | ''
    public string $filterPeriod = 'all'; // 'today' | 'week' | 'month' | 'all'

    // Summary stats
    public float  $totalProfit     = 0;
    public float  $todayProfit     = 0;
    public float  $weekProfit      = 0;
    public int    $totalTrades     = 0;
    public int    $winTrades       = 0;
    public int    $lossTrades      = 0;
    public float  $winRate         = 0;
    public float  $bestTrade       = 0;
    public float  $worstTrade      = 0;

    public function mount(): void
    {
        $this->bot = Bot::firstOrFail();
        $this->loadStats();
    }

    public function updatedFilterPair(): void   { $this->resetPage(); }
    public function updatedFilterResult(): void { $this->resetPage(); }
    public function updatedFilterPeriod(): void { $this->resetPage(); }

    private function loadStats(): void
    {
        $base = Trade::where('bot_id', $this->bot->id)->where('status', 'closed');

        $this->totalProfit  = (float) $base->clone()->sum('profit_loss');
        $this->todayProfit  = (float) $base->clone()->whereDate('closed_at', today())->sum('profit_loss');
        $this->weekProfit   = (float) $base->clone()->where('closed_at', '>=', now()->startOfWeek())->sum('profit_loss');
        $this->totalTrades  = $base->clone()->count();
        $this->winTrades    = $base->clone()->where('profit_loss', '>', 0)->count();
        $this->lossTrades   = $base->clone()->where('profit_loss', '<', 0)->count();
        $this->winRate      = $this->totalTrades > 0 ? round($this->winTrades / $this->totalTrades * 100, 1) : 0;
        $this->bestTrade    = (float) ($base->clone()->max('profit_loss') ?? 0);
        $this->worstTrade   = (float) ($base->clone()->min('profit_loss') ?? 0);
    }

    private function baseQuery()
    {
        $q = Trade::where('bot_id', $this->bot->id)->where('status', 'closed');

        if ($this->filterPair) {
            $q->where('binance_pair', $this->filterPair);
        }

        if ($this->filterResult === 'profit') {
            $q->where('profit_loss', '>', 0);
        } elseif ($this->filterResult === 'loss') {
            $q->where('profit_loss', '<', 0);
        }

        if ($this->filterPeriod === 'today') {
            $q->whereDate('closed_at', today());
        } elseif ($this->filterPeriod === 'week') {
            $q->where('closed_at', '>=', now()->startOfWeek());
        } elseif ($this->filterPeriod === 'month') {
            $q->where('closed_at', '>=', now()->startOfMonth());
        }

        return $q;
    }

    public function render()
    {
        $this->loadStats();

        $trades = $this->baseQuery()
            ->orderByDesc('closed_at')
            ->paginate(15);

        $pairs = Trade::where('bot_id', $this->bot->id)
            ->where('status', 'closed')
            ->distinct()
            ->pluck('binance_pair');

        return view('livewire.profit-history', compact('trades', 'pairs'));
    }
}
