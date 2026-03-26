<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Market Scan</h1>
            <p class="text-xs text-slate-400 mt-0.5">Analisis sinyal pasar secara manual — tidak langsung eksekusi</p>
        </div>
        @if($scannedAt)
            <span class="text-xs text-slate-500 bg-slate-100 px-3 py-1.5 rounded-lg font-medium">
                Terakhir scan: {{ $scannedAt }}
            </span>
        @endif
    </div>

    {{-- Scan Button --}}
    <button wire:click="scan" wire:loading.attr="disabled"
            class="inline-flex items-center gap-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed
                   text-white font-semibold px-6 py-2.5 rounded-xl text-sm transition-all shadow-sm">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
             wire:loading.class="animate-spin" wire:target="scan">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        <span wire:loading.remove wire:target="scan">Scan Sekarang</span>
        <span wire:loading wire:target="scan">Scanning...</span>
    </button>

    {{-- Error --}}
    @if($error)
        <div class="flex items-start gap-2.5 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div>
                <p class="font-semibold">Scan Gagal</p>
                <p class="mt-0.5 text-red-600 text-xs">{{ $error }}</p>
            </div>
        </div>
    @endif

    {{-- ── LOADING STATE ── --}}
    <div wire:loading wire:target="scan">

        {{-- Progress Steps --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-4">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-blue-600 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">Sedang menganalisis pasar...</p>
                    <p class="text-xs text-slate-400 mt-0.5">Mengambil data dari Gate.io & Indodax</p>
                </div>
            </div>

            {{-- Step indicators --}}
            <div class="space-y-2.5">
                @php
                    $steps = [
                        ['Mengambil data kline (OHLC) dari Gate.io', 'blue'],
                        ['Menghitung indikator EMA & RSI', 'blue'],
                        ['Mengambil harga IDR dari Indodax', 'blue'],
                        ['Menganalisis sinyal BUY / SELL / HOLD', 'blue'],
                    ];
                @endphp
                @foreach($steps as $i => [$label, $color])
                    <div class="flex items-center gap-3">
                        <div class="w-5 h-5 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                            <div class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"
                                 style="animation-delay: {{ $i * 200 }}ms"></div>
                        </div>
                        <span class="text-xs text-slate-600">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Skeleton Cards --}}
        @php $pairCount = count($bot->settings?->pairs ?? ['','','']); @endphp
        @for($s = 0; $s < $pairCount; $s++)
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-4 animate-pulse">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div class="flex items-center gap-3.5">
                        <div class="w-10 h-10 rounded-xl bg-slate-200"></div>
                        <div class="space-y-2">
                            <div class="h-4 w-20 bg-slate-200 rounded"></div>
                            <div class="h-3 w-28 bg-slate-100 rounded"></div>
                        </div>
                    </div>
                    <div class="h-8 w-16 bg-slate-200 rounded-xl"></div>
                </div>
                <div class="grid grid-cols-4 divide-x divide-slate-100">
                    @for($c = 0; $c < 4; $c++)
                        <div class="px-5 py-4 space-y-2">
                            <div class="h-2.5 w-12 bg-slate-100 rounded"></div>
                            <div class="h-4 w-16 bg-slate-200 rounded"></div>
                            <div class="h-2.5 w-14 bg-slate-100 rounded"></div>
                        </div>
                    @endfor
                </div>
            </div>
        @endfor
    </div>

    {{-- ── RESULTS ── --}}
    <div wire:loading.remove wire:target="scan">
        @if(count($results) > 0)
            <div class="space-y-4">

                {{-- Summary bar --}}
                @php
                    $buyCount  = count(array_filter($results, fn($r) => $r['signal'] === 'buy'));
                    $sellCount = count(array_filter($results, fn($r) => $r['signal'] === 'sell'));
                    $holdCount = count(array_filter($results, fn($r) => $r['signal'] === 'hold'));
                @endphp
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-5 py-3.5 flex items-center gap-4 flex-wrap">
                    <span class="text-xs font-semibold text-slate-500">{{ count($results) }} pair dianalisis</span>
                    <div class="flex items-center gap-3 ml-auto">
                        @if($buyCount)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse inline-block"></span>
                                {{ $buyCount }} BUY
                            </span>
                        @endif
                        @if($sellCount)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse inline-block"></span>
                                {{ $sellCount }} SELL
                            </span>
                        @endif
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-500">
                            {{ $holdCount }} HOLD
                        </span>
                    </div>
                </div>

                {{-- Result cards --}}
                @foreach($results as $result)
                    @php
                        $signal      = $result['signal'];
                        $indicators  = $result['indicators'] ?? [];
                        $idrPrice    = $result['idr_price'] ?? 0;
                        $hasError    = ! empty($result['error']);
                        $displayPair = $result['display_pair'] ?? $result['pair'];
                        $emaFast     = $indicators['ema_fast'] ?? 0;
                        $emaSlow     = $indicators['ema_slow'] ?? 0;
                        $rsi         = $indicators['rsi'] ?? 50;
                        $isBullish   = $indicators['is_bullish'] ?? false;
                    @endphp

                    <div class="bg-white rounded-2xl border shadow-sm overflow-hidden
                        {{ $signal === 'buy'  ? 'border-emerald-200' :
                           ($signal === 'sell' ? 'border-red-200' : 'border-slate-100') }}">

                        {{-- Card Header --}}
                        <div class="flex items-center justify-between px-5 py-4 border-b
                            {{ $signal === 'buy'  ? 'border-emerald-100 bg-emerald-50/40' :
                               ($signal === 'sell' ? 'border-red-100 bg-red-50/30' : 'border-slate-100') }}">
                            <div class="flex items-center gap-3.5">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-sm shrink-0
                                    {{ $signal === 'buy'  ? 'bg-emerald-100 text-emerald-700' :
                                       ($signal === 'sell' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600') }}">
                                    {{ substr($displayPair, 0, 3) }}
                                </div>
                                <div>
                                    <p class="font-bold text-slate-900">{{ $displayPair }}</p>
                                    @if($idrPrice > 0)
                                        <p class="text-sm text-slate-500 font-mono mt-0.5">
                                            Rp {{ number_format($idrPrice, 0, ',', '.') }}
                                        </p>
                                    @elseif($hasError)
                                        <p class="text-xs text-red-500 mt-0.5">Gagal mengambil data</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Signal Badge --}}
                            @if($hasError)
                                <span class="px-4 py-1.5 rounded-xl text-xs font-bold bg-slate-100 text-slate-500">ERROR</span>
                            @elseif($signal === 'buy')
                                <span class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-xl text-sm font-bold bg-emerald-500 text-white shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span> BUY
                                </span>
                            @elseif($signal === 'sell')
                                <span class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-xl text-sm font-bold bg-red-500 text-white shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span> SELL
                                </span>
                            @else
                                <span class="px-4 py-1.5 rounded-xl text-sm font-bold bg-slate-100 text-slate-500 border border-slate-200">HOLD</span>
                            @endif
                        </div>

                        @if($hasError)
                            <div class="px-5 py-4 text-sm text-red-600 bg-red-50">{{ $result['error'] }}</div>
                        @elseif(! empty($indicators))
                            {{-- Indicators Grid --}}
                            <div class="grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 divide-x divide-slate-100">
                                <div class="px-5 py-4">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1.5">
                                        EMA {{ $bot->settings?->ema_fast ?? 20 }}
                                    </p>
                                    <p class="font-bold text-slate-900 text-sm font-mono">{{ number_format($emaFast, 2) }}</p>
                                    <p class="text-[11px] mt-1 font-medium {{ $emaFast > $emaSlow ? 'text-emerald-600' : 'text-red-500' }}">
                                        {{ $emaFast > $emaSlow ? '▲ di atas' : '▼ di bawah' }} EMA{{ $bot->settings?->ema_slow ?? 50 }}
                                    </p>
                                </div>

                                <div class="px-5 py-4">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1.5">
                                        EMA {{ $bot->settings?->ema_slow ?? 50 }}
                                    </p>
                                    <p class="font-bold text-slate-900 text-sm font-mono">{{ number_format($emaSlow, 2) }}</p>
                                    @php $priceAbove = ($idrPrice > 0 ? $idrPrice : ($indicators['current_price'] ?? 0)) > $emaSlow; @endphp
                                    <p class="text-[11px] mt-1 font-medium {{ $priceAbove ? 'text-emerald-600' : 'text-red-500' }}">
                                        Harga {{ $priceAbove ? '▲ di atas' : '▼ di bawah' }}
                                    </p>
                                </div>

                                <div class="px-5 py-4">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1.5">
                                        RSI {{ $bot->settings?->rsi_period ?? 14 }}
                                    </p>
                                    @php
                                        $rsiColor = $rsi > 70 ? 'text-red-600' : ($rsi < 30 ? 'text-blue-600' : ($rsi >= 40 && $rsi <= 60 ? 'text-emerald-600' : 'text-amber-600'));
                                        $rsiLabel = $rsi > 70 ? 'Overbought' : ($rsi < 30 ? 'Oversold' : ($rsi >= 40 && $rsi <= 60 ? 'Zona Buy' : 'Netral'));
                                        $rsiWidth = min(100, max(0, $rsi));
                                    @endphp
                                    <p class="font-bold text-slate-900 text-sm">{{ $rsi }}</p>
                                    <div class="flex items-center gap-1.5 mt-1.5">
                                        <div class="flex-1 h-1 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-1 rounded-full {{ $rsi > 70 ? 'bg-red-500' : ($rsi < 30 ? 'bg-blue-500' : 'bg-emerald-500') }}"
                                                 style="width:{{ $rsiWidth }}%"></div>
                                        </div>
                                        <span class="text-[10px] font-semibold {{ $rsiColor }}">{{ $rsiLabel }}</span>
                                    </div>
                                </div>

                                <div class="px-5 py-4">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1.5">
                                        Candle {{ $bot->settings?->kline_interval ?? '5m' }}
                                    </p>
                                    <p class="font-bold text-sm {{ $isBullish ? 'text-emerald-600' : 'text-red-500' }}">
                                        {{ $isBullish ? '▲ Bullish' : '▼ Bearish' }}
                                    </p>
                                    <p class="text-[11px] mt-1 text-slate-400">
                                        Close {{ $isBullish ? '>' : '<' }} Open
                                    </p>
                                </div>
                            </div>

                            {{-- Strategy Conditions --}}
                            <div class="px-5 py-3 bg-slate-50/60 border-t border-slate-100">
                                @php
                                    $currentPrice = $idrPrice > 0 ? $idrPrice : ($indicators['current_price'] ?? 0);
                                    $conditions   = [
                                        [$currentPrice > $emaSlow,  'Harga > EMA' . ($bot->settings?->ema_slow ?? 50)],
                                        [$emaFast > $emaSlow,       'EMA' . ($bot->settings?->ema_fast ?? 20) . ' > EMA' . ($bot->settings?->ema_slow ?? 50)],
                                        [$rsi >= 40 && $rsi <= 60,  'RSI 40–60 (' . $rsi . ')'],
                                        [$isBullish,                'Candle Bullish'],
                                    ];
                                    $metCount = count(array_filter($conditions, fn($c) => $c[0]));
                                @endphp
                                <div class="flex items-center gap-2 mb-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Kondisi Strategi</p>
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded
                                        {{ $metCount === 4 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $metCount }}/4 terpenuhi
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($conditions as [$ok, $label])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-medium
                                            {{ $ok ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                   : 'bg-slate-100 text-slate-400 border border-slate-200' }}">
                                            {{ $ok ? '✓' : '✗' }} {{ $label }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

        @elseif($scannedAt !== '')
            {{-- Scanned but no results --}}
            <div class="text-center py-12 bg-white rounded-2xl border border-slate-100 shadow-sm">
                <div class="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>
                <p class="text-slate-600 font-medium text-sm">Tidak ada data ditemukan</p>
                <p class="text-xs text-slate-400 mt-1">Pastikan pair sudah dikonfigurasi di Settings</p>
            </div>

        @else
            {{-- Initial empty state --}}
            <div class="text-center py-16 bg-white rounded-2xl border border-slate-100 shadow-sm">
                <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <p class="text-slate-700 font-semibold">Klik "Scan Sekarang" untuk melihat sinyal</p>
                <p class="text-sm text-slate-400 mt-1.5">Data kline dari Gate.io · Harga IDR dari Indodax</p>
                @if($bot->settings?->pairs)
                    <div class="flex flex-wrap justify-center gap-1.5 mt-4">
                        @foreach($bot->settings->pairs as $p)
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 text-[11px] font-semibold">
                                {{ app(\App\Services\BinanceService::class)->getDisplayName($p) }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>

</div>
