<div wire:poll.30s="refresh" class="space-y-6">

    {{-- ── Page Header ── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Dashboard</h1>
            <p class="text-xs text-slate-400 mt-0.5">Auto refresh setiap 30 detik</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border
            {{ $botMode === 'simulation'
                ? 'bg-amber-50 text-amber-700 border-amber-200'
                : 'bg-blue-50 text-blue-700 border-blue-200' }}">
            {{ $botMode === 'simulation' ? '⚡ SIMULASI' : '🔴 REAL' }}
        </span>
    </div>

    {{-- Feedback --}}
    @if($successMsg)
        <div class="flex items-center gap-2 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            {{ $successMsg }}
        </div>
    @endif

    @if($scanError)
        <div class="flex items-start gap-2 p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            {{ $scanError }}
        </div>
    @endif

    {{-- ── Stats Cards ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- Status --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Status Bot</p>
            <div class="flex items-center gap-2">
                <div class="w-2.5 h-2.5 rounded-full shrink-0
                    {{ $botStatus === 'running' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300' }}"></div>
                <span class="text-lg font-bold {{ $botStatus === 'running' ? 'text-emerald-600' : 'text-slate-400' }}">
                    {{ $botStatus === 'running' ? 'Aktif' : 'Berhenti' }}
                </span>
            </div>
        </div>

        {{-- Balance --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">
                Saldo IDR{{ $botMode === 'simulation' ? ' (Sim)' : '' }}
            </p>
            <p class="text-lg font-bold text-slate-900">
                Rp {{ number_format($idrBalance, 0, ',', '.') }}
            </p>
            @if($botMode === 'real' && $idrBalanceHold > 0)
                <p class="text-[11px] text-orange-500 mt-1">
                    Hold: Rp {{ number_format($idrBalanceHold, 0, ',', '.') }}
                </p>
            @endif
        </div>

        {{-- Open Positions --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Posisi Terbuka</p>
            <div class="flex items-baseline gap-1.5">
                <span class="text-lg font-bold {{ $openCount > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ $openCount }}</span>
                <span class="text-sm text-slate-400">/ {{ $maxPositions }} slot</span>
            </div>
        </div>

        {{-- Today Profit --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Profit Hari Ini</p>
            <p class="text-lg font-bold {{ $todayProfit >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                {{ $todayProfit >= 0 ? '+' : '' }}Rp {{ number_format($todayProfit, 0, ',', '.') }}
            </p>
        </div>
    </div>

    {{-- ── Portfolio Allocation ── --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Portfolio Allocation</h3>
                <p class="text-xs text-slate-400 mt-0.5">
                    Total Modal: <span class="font-semibold text-slate-700">Rp {{ number_format($totalCapital, 0, ',', '.') }}</span>
                    &nbsp;·&nbsp; Per Posisi: <span class="font-semibold text-blue-600">Rp {{ number_format($perPositionAlloc, 0, ',', '.') }}</span>
                </p>
            </div>
            <span class="text-sm font-bold {{ $utilizationPct >= 80 ? 'text-amber-600' : 'text-slate-700' }}">
                {{ $utilizationPct }}% dipakai
            </span>
        </div>

        {{-- Progress Bar --}}
        <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
            <div class="h-3 rounded-full transition-all duration-500
                {{ $utilizationPct >= 80 ? 'bg-amber-400' : 'bg-blue-500' }}"
                 style="width: {{ min(100, $utilizationPct) }}%"></div>
        </div>

        {{-- Slot Indicators --}}
        <div class="flex items-center gap-2 mt-3">
            @for($i = 1; $i <= $maxPositions; $i++)
                <div class="flex-1 h-2 rounded-full {{ $i <= $openCount ? 'bg-blue-500' : 'bg-slate-200' }}"></div>
            @endfor
            <span class="text-[11px] text-slate-400 ml-1 shrink-0">
                {{ $openCount }}/{{ $maxPositions }} slot
            </span>
        </div>

        {{-- Stats row --}}
        <div class="grid grid-cols-{{ $botMode === 'real' ? '4' : '3' }} gap-3 mt-4 pt-4 border-t border-slate-100">
            <div class="text-center">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1">Bebas</p>
                <p class="text-sm font-bold text-emerald-600">Rp {{ number_format($idrBalance, 0, ',', '.') }}</p>
            </div>
            @if($botMode === 'real')
            <div class="text-center border-x border-slate-100">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1">Hold Indodax</p>
                <p class="text-sm font-bold text-orange-500">Rp {{ number_format($idrBalanceHold, 0, ',', '.') }}</p>
            </div>
            @endif
            <div class="text-center {{ $botMode === 'real' ? '' : 'border-x border-slate-100' }}">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1">Terkunci (Bot)</p>
                <p class="text-sm font-bold text-amber-600">Rp {{ number_format($lockedIdr, 0, ',', '.') }}</p>
            </div>
            <div class="text-center {{ $botMode === 'real' ? 'border-l border-slate-100' : '' }}">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1">Total</p>
                <p class="text-sm font-bold text-slate-800">Rp {{ number_format($totalCapital, 0, ',', '.') }}</p>
            </div>
        </div>
    </div>

    {{-- ── Action Buttons ── --}}
    <div class="flex flex-wrap gap-2.5">
        @if($botStatus === 'stopped')
            <button wire:click="startBot" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60
                           text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span wire:loading.remove wire:target="startBot">Start Bot</span>
                <span wire:loading wire:target="startBot">Starting...</span>
            </button>
        @else
            <button wire:click="stopBot" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 bg-red-500 hover:bg-red-600 disabled:opacity-60
                           text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                </svg>
                <span wire:loading.remove wire:target="stopBot">Stop Bot</span>
                <span wire:loading wire:target="stopBot">Stopping...</span>
            </button>
        @endif

        <button wire:click="scanMarket" wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 bg-white hover:bg-slate-50 disabled:opacity-60
                       border border-slate-200 text-slate-700 font-medium px-5 py-2.5 rounded-xl text-sm transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                 wire:loading.class="animate-spin" wire:target="scanMarket">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <span wire:loading.remove wire:target="scanMarket">Scan Market</span>
            <span wire:loading wire:target="scanMarket">Scanning...</span>
        </button>

        <a href="{{ route('manual-scan') }}"
           class="inline-flex items-center gap-2 bg-white hover:bg-slate-50 border border-slate-200
                  text-slate-600 font-medium px-5 py-2.5 rounded-xl text-sm transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
            Detail Scan
        </a>
    </div>

    {{-- ── Quick Scan Results ── --}}
    @if(count($scanResults) > 0)
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-800">Hasil Scan Market</h3>
                <span class="text-xs text-slate-400">{{ now()->format('H:i:s') }}</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-0 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                @foreach($scanResults as $result)
                    @php
                        $signal = $result['signal'];
                        $hasErr = ! empty($result['error']);
                    @endphp
                    <div class="p-4 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-slate-900">{{ $result['display_pair'] ?? $result['pair'] }}</p>
                            @if(! $hasErr && ! empty($result['idr_price']))
                                <p class="text-xs text-slate-400 mt-0.5">
                                    Rp {{ number_format($result['idr_price'], 0, ',', '.') }}
                                    @if(!empty($result['indicators']['rsi']))
                                        · RSI {{ $result['indicators']['rsi'] }}
                                    @endif
                                </p>
                            @elseif($hasErr)
                                <p class="text-xs text-red-500 mt-0.5">{{ $result['error'] }}</p>
                            @endif
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold
                            @if($signal === 'buy') bg-emerald-100 text-emerald-700 border border-emerald-200
                            @elseif($signal === 'sell') bg-red-100 text-red-700 border border-red-200
                            @else bg-slate-100 text-slate-500 border border-slate-200 @endif">
                            {{ strtoupper($signal) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── Open Positions ── --}}
    @if($openCount > 0)
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></div>
                    <h3 class="text-sm font-semibold text-slate-800">Posisi Terbuka ({{ $openCount }})</h3>
                </div>
                <button wire:click="closeAllPositions"
                        wire:loading.attr="disabled"
                        wire:confirm="Yakin ingin menjual semua {{ $openCount }} posisi terbuka sekarang?"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold
                               bg-red-50 text-red-600 hover:bg-red-100 disabled:opacity-50 transition">
                    <svg wire:loading.remove wire:target="closeAllPositions" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <svg wire:loading wire:target="closeAllPositions" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <span wire:loading.remove wire:target="closeAllPositions">Jual Semua</span>
                    <span wire:loading wire:target="closeAllPositions">Menjual...</span>
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-xs text-slate-500 font-semibold uppercase tracking-wide">
                            <th class="text-left px-5 py-3">Pair</th>
                            <th class="text-right px-4 py-3">Harga Masuk</th>
                            <th class="text-right px-4 py-3">Harga Kini</th>
                            <th class="text-right px-4 py-3">P/L</th>
                            <th class="text-right px-4 py-3">Stop Loss</th>
                            <th class="text-right px-4 py-3">Take Profit</th>
                            <th class="text-right px-4 py-3">Modal IDR</th>
                            <th class="text-right px-4 py-3">Waktu</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($openTradesWithPl as $trade)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-5 py-3.5">
                                    <span class="font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded text-xs">
                                        {{ \App\Services\BinanceService::class ? app(\App\Services\BinanceService::class)->getDisplayName($trade['binance_pair']) : $trade['binance_pair'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-right text-slate-700 font-mono text-xs">
                                    Rp {{ number_format($trade['entry_price'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-xs text-slate-700">
                                    @if($trade['current_price'] > 0)
                                        Rp {{ number_format($trade['current_price'], 0, ',', '.') }}
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right text-xs font-bold">
                                    @if($trade['unrealized_pl'] !== null)
                                        @php $pl = $trade['unrealized_pl']; $plp = $trade['unrealized_pl_percent']; @endphp
                                        <span class="{{ $pl >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                                            {{ $pl >= 0 ? '+' : '' }}Rp {{ number_format($pl, 0, ',', '.') }}
                                        </span>
                                        <br>
                                        <span class="font-semibold text-[10px] {{ $plp >= 0 ? 'text-emerald-500' : 'text-red-400' }}">
                                            {{ $plp >= 0 ? '+' : '' }}{{ $plp }}%
                                        </span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right text-red-600 font-mono text-xs">
                                    Rp {{ number_format($trade['stop_loss_price'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3.5 text-right text-emerald-600 font-mono text-xs">
                                    Rp {{ number_format($trade['take_profit_price'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3.5 text-right text-slate-700 font-mono text-xs">
                                    Rp {{ number_format($trade['amount_idr'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3.5 text-right text-slate-400 text-xs">
                                    {{ \Carbon\Carbon::parse($trade['created_at'])->diffForHumans() }}
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <button wire:click="closePosition({{ $trade['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:confirm="Jual {{ $trade['binance_pair'] }} sekarang?"
                                            class="px-2.5 py-1 rounded-lg text-xs font-semibold
                                                   bg-red-50 text-red-600 hover:bg-red-100 disabled:opacity-50 transition">
                                        <span wire:loading.remove wire:target="closePosition({{ $trade['id'] }})">Jual</span>
                                        <span wire:loading wire:target="closePosition({{ $trade['id'] }})">...</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ── Trade History ── --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-800">Riwayat Trade</h3>
        </div>

        @if(count($recentTrades) === 0)
            <div class="text-center py-12">
                <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <p class="text-sm text-slate-400">Belum ada riwayat trade.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-xs text-slate-500 font-semibold uppercase tracking-wide">
                            <th class="text-left px-5 py-3">Pair</th>
                            <th class="text-right px-4 py-3">Harga Masuk</th>
                            <th class="text-right px-4 py-3">Harga Keluar</th>
                            <th class="text-right px-4 py-3">P/L (IDR)</th>
                            <th class="text-right px-4 py-3">%</th>
                            <th class="text-left px-4 py-3">Alasan</th>
                            <th class="text-right px-4 py-3">Waktu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($recentTrades as $trade)
                            @php $pl = $trade['profit_loss'] ?? 0; $plp = $trade['profit_loss_percent'] ?? 0; @endphp
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-5 py-3.5 font-semibold text-slate-800 text-xs">
                                    {{ app(\App\Services\BinanceService::class)->getDisplayName($trade['binance_pair']) }}
                                </td>
                                <td class="px-4 py-3.5 text-right text-slate-500 font-mono text-xs">
                                    Rp {{ number_format($trade['entry_price'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3.5 text-right text-slate-500 font-mono text-xs">
                                    Rp {{ number_format($trade['exit_price'] ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-bold text-xs
                                    {{ $pl >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                                    {{ $pl >= 0 ? '+' : '' }}Rp {{ number_format($pl, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3.5 text-right text-xs font-semibold
                                    {{ $plp >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                                    {{ $plp >= 0 ? '+' : '' }}{{ number_format($plp, 2) }}%
                                </td>
                                <td class="px-4 py-3.5 text-xs">
                                    @php $reason = $trade['close_reason'] ?? '-'; @endphp
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold
                                        {{ $reason === 'take_profit' ? 'bg-emerald-100 text-emerald-700'
                                           : ($reason === 'stop_loss' ? 'bg-red-100 text-red-600'
                                           : 'bg-slate-100 text-slate-600') }}">
                                        {{ str_replace('_', ' ', $reason) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-right text-slate-400 text-xs">
                                    {{ \Carbon\Carbon::parse($trade['closed_at'])->format('d/m H:i') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ── Bot Logs ── --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden"
         x-data="{ selectAll: false }"
         x-on:selectAll.window="selectAll = $event.detail">
        <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2.5">
                <h3 class="text-sm font-semibold text-slate-800">Log Bot</h3>
                <span class="text-xs text-slate-400">{{ count($recentLogs) }} entri</span>
                @if(count($selectedLogIds) > 0)
                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-bold rounded-full">
                        {{ count($selectedLogIds) }} dipilih
                    </span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @if(count($selectedLogIds) > 0)
                    <button wire:click="deleteSelectedLogs"
                            wire:confirm="Hapus {{ count($selectedLogIds) }} log yang dipilih?"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold
                                   bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 transition">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Hapus Dipilih
                    </button>
                @endif
                @if(count($recentLogs) > 0)
                    <button wire:click="deleteAllLogs"
                            wire:confirm="Hapus semua log bot? Tindakan ini tidak dapat dibatalkan."
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold
                                   bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200 transition">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Hapus Semua
                    </button>
                @endif
            </div>
        </div>

        {{-- Select All row --}}
        @if(count($recentLogs) > 0)
        <div class="px-5 py-2 border-b border-slate-50 bg-slate-50/50 flex items-center gap-2.5">
            <input type="checkbox" class="w-3.5 h-3.5 accent-blue-600"
                   x-model="selectAll"
                   x-on:change="
                       if (selectAll) {
                           $wire.set('selectedLogIds', {{ json_encode(array_column($recentLogs, 'id')) }})
                       } else {
                           $wire.set('selectedLogIds', [])
                       }
                   ">
            <span class="text-[10px] font-semibold text-slate-500">Pilih Semua</span>
        </div>
        @endif

        <div class="divide-y divide-slate-50 max-h-80 overflow-y-auto">
            @forelse($recentLogs as $log)
                @php
                    $isBuySell = str_contains($log['message'], 'BUY ') || str_contains($log['message'], 'SELL ');
                    $isSim     = str_starts_with($log['message'], '[SIMULASI]');
                @endphp
                <div class="flex items-start gap-3 px-5 py-2.5 hover:bg-slate-50/50 transition-colors
                    {{ $isBuySell ? 'bg-blue-50/30' : '' }}">
                    <input type="checkbox"
                           wire:model="selectedLogIds"
                           value="{{ $log['id'] }}"
                           class="w-3.5 h-3.5 accent-blue-600 mt-0.5 shrink-0">
                    <span class="text-[10px] text-slate-400 font-mono pt-0.5 shrink-0 w-14">
                        {{ \Carbon\Carbon::parse($log['created_at'])->format('H:i:s') }}
                    </span>
                    <div class="flex items-start gap-1.5 flex-1 min-w-0">
                        <span class="shrink-0 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide mt-0.5
                            {{ $log['level'] === 'error'   ? 'bg-red-100 text-red-600'
                               : ($log['level'] === 'warning' ? 'bg-amber-100 text-amber-700'
                               : 'bg-blue-100 text-blue-700') }}">
                            {{ $log['level'] }}
                        </span>
                        @if($isSim)
                            <span class="shrink-0 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide mt-0.5 bg-amber-100 text-amber-700">SIM</span>
                        @endif
                        <span class="text-xs text-slate-700 break-all">
                            {{ $isSim ? ltrim(substr($log['message'], strlen('[SIMULASI]'))) : $log['message'] }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-slate-400 text-sm">Belum ada log.</div>
            @endforelse
        </div>
    </div>

</div>
