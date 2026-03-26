<div class="space-y-6">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Profit & Riwayat</h1>
                <p class="text-sm text-slate-500">Statistik dan riwayat semua trade yang telah ditutup</p>
            </div>
            <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $bot->isSimulation() ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                {{ $bot->isSimulation() ? 'SIMULASI' : 'LIVE' }}
            </span>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

            {{-- Total Profit --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Total Profit</p>
                <p class="mt-1 text-2xl font-bold {{ $totalProfit >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                    {{ $totalProfit >= 0 ? '+' : '' }}Rp {{ number_format($totalProfit, 0, ',', '.') }}
                </p>
                <p class="mt-1 text-xs text-slate-400">Semua waktu</p>
            </div>

            {{-- Hari Ini --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Hari Ini</p>
                <p class="mt-1 text-2xl font-bold {{ $todayProfit >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                    {{ $todayProfit >= 0 ? '+' : '' }}Rp {{ number_format($todayProfit, 0, ',', '.') }}
                </p>
                <p class="mt-1 text-xs text-slate-400">{{ now()->format('d M Y') }}</p>
            </div>

            {{-- Minggu Ini --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Minggu Ini</p>
                <p class="mt-1 text-2xl font-bold {{ $weekProfit >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                    {{ $weekProfit >= 0 ? '+' : '' }}Rp {{ number_format($weekProfit, 0, ',', '.') }}
                </p>
                <p class="mt-1 text-xs text-slate-400">{{ now()->startOfWeek()->format('d M') }} – {{ now()->format('d M') }}</p>
            </div>

            {{-- Win Rate --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Win Rate</p>
                <p class="mt-1 text-2xl font-bold text-slate-800">{{ $winRate }}%</p>
                <p class="mt-1 text-xs text-slate-400">{{ $winTrades }} menang / {{ $lossTrades }} rugi / {{ $totalTrades }} total</p>
            </div>

        </div>

        {{-- Best / Worst + Bar --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 flex items-center gap-4">
                <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Trade Terbaik</p>
                    <p class="text-lg font-bold text-emerald-600">+Rp {{ number_format($bestTrade, 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 flex items-center gap-4">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Trade Terburuk</p>
                    <p class="text-lg font-bold text-red-500">Rp {{ number_format($worstTrade, 0, ',', '.') }}</p>
                </div>
            </div>

        </div>

        {{-- Win/Loss Progress Bar --}}
        @if($totalTrades > 0)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <div class="flex justify-between text-xs text-slate-500 mb-2">
                <span>{{ $winTrades }} Profit</span>
                <span>{{ $lossTrades }} Loss</span>
            </div>
            <div class="w-full bg-red-100 rounded-full h-3 overflow-hidden">
                <div class="bg-emerald-500 h-3 rounded-full transition-all duration-500"
                     style="width: {{ $winRate }}%"></div>
            </div>
            <p class="text-center text-xs text-slate-400 mt-2">Win Rate: {{ $winRate }}%</p>
        </div>
        @endif

        {{-- Filter --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">

                {{-- Filter Pair --}}
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Pair</label>
                    <select wire:model.live="filterPair"
                            class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Semua Pair</option>
                        @foreach($pairs as $p)
                            <option value="{{ $p }}">{{ str_replace('USDT', '/IDR', $p) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Hasil --}}
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Hasil</label>
                    <select wire:model.live="filterResult"
                            class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Semua</option>
                        <option value="profit">Profit saja</option>
                        <option value="loss">Loss saja</option>
                    </select>
                </div>

                {{-- Filter Periode --}}
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Periode</label>
                    <select wire:model.live="filterPeriod"
                            class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="all">Semua Waktu</option>
                        <option value="today">Hari Ini</option>
                        <option value="week">Minggu Ini</option>
                        <option value="month">Bulan Ini</option>
                    </select>
                </div>

            </div>
        </div>

        {{-- Trade Table --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="font-semibold text-slate-700">Riwayat Trade</h2>
                <span class="text-xs text-slate-400">{{ $trades->total() }} trade</span>
            </div>

            @if($trades->isEmpty())
                <div class="p-10 text-center text-slate-400">
                    <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm">Belum ada riwayat trade</p>
                </div>
            @else

                {{-- Desktop Table --}}
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                            <tr>
                                <th class="px-4 py-3 text-left">Pair</th>
                                <th class="px-4 py-3 text-right">Harga Beli</th>
                                <th class="px-4 py-3 text-right">Harga Jual</th>
                                <th class="px-4 py-3 text-right">Modal</th>
                                <th class="px-4 py-3 text-right">Profit/Loss</th>
                                <th class="px-4 py-3 text-center">Alasan</th>
                                <th class="px-4 py-3 text-right">Ditutup</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($trades as $trade)
                                @php
                                    $pl    = (float) ($trade->profit_loss ?? 0);
                                    $plp   = (float) ($trade->profit_loss_percent ?? 0);
                                    $isWin = $pl > 0;
                                @endphp
                                <tr class="hover:bg-slate-50 transition-colors {{ $isWin ? 'bg-emerald-50/30' : 'bg-red-50/30' }}">
                                    <td class="px-4 py-3 font-semibold text-slate-800">
                                        {{ str_replace('USDT', '/IDR', $trade->binance_pair) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-600">
                                        Rp {{ number_format($trade->entry_price, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-600">
                                        Rp {{ number_format($trade->exit_price, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-600">
                                        Rp {{ number_format($trade->amount_idr, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold {{ $isWin ? 'text-emerald-600' : 'text-red-500' }}">
                                        {{ $pl >= 0 ? '+' : '' }}Rp {{ number_format($pl, 0, ',', '.') }}
                                        <span class="block text-xs font-normal">
                                            {{ $plp >= 0 ? '+' : '' }}{{ number_format($plp, 2) }}%
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @php $reason = $trade->close_reason ?? '-'; @endphp
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                            {{ $reason === 'take_profit' ? 'bg-emerald-100 text-emerald-700' :
                                               ($reason === 'stop_loss'  ? 'bg-red-100 text-red-700' :
                                                'bg-slate-100 text-slate-600') }}">
                                            {{ $reason === 'take_profit' ? 'Take Profit' :
                                               ($reason === 'stop_loss'  ? 'Stop Loss' :
                                                ($reason === 'signal_sell' ? 'Signal Jual' : ucfirst($reason))) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-400 text-xs">
                                        {{ \Carbon\Carbon::parse($trade->closed_at)->format('d/m H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Cards --}}
                <div class="sm:hidden divide-y divide-slate-100">
                    @foreach($trades as $trade)
                        @php
                            $pl    = (float) ($trade->profit_loss ?? 0);
                            $plp   = (float) ($trade->profit_loss_percent ?? 0);
                            $isWin = $pl > 0;
                        @endphp
                        <div class="p-4 {{ $isWin ? 'bg-emerald-50/30' : 'bg-red-50/30' }}">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-bold text-slate-800">{{ str_replace('USDT', '/IDR', $trade->binance_pair) }}</span>
                                <span class="font-bold text-base {{ $isWin ? 'text-emerald-600' : 'text-red-500' }}">
                                    {{ $pl >= 0 ? '+' : '' }}Rp {{ number_format($pl, 0, ',', '.') }}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-1 text-xs text-slate-500">
                                <span>Beli: Rp {{ number_format($trade->entry_price, 0, ',', '.') }}</span>
                                <span>Jual: Rp {{ number_format($trade->exit_price, 0, ',', '.') }}</span>
                                <span>Modal: Rp {{ number_format($trade->amount_idr, 0, ',', '.') }}</span>
                                <span class="{{ $isWin ? 'text-emerald-600' : 'text-red-500' }}">{{ $plp >= 0 ? '+' : '' }}{{ number_format($plp, 2) }}%</span>
                            </div>
                            <div class="flex justify-between items-center mt-2">
                                @php $reason = $trade->close_reason ?? '-'; @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $reason === 'take_profit' ? 'bg-emerald-100 text-emerald-700' :
                                       ($reason === 'stop_loss'  ? 'bg-red-100 text-red-700' :
                                        'bg-slate-100 text-slate-600') }}">
                                    {{ $reason === 'take_profit' ? 'Take Profit' :
                                       ($reason === 'stop_loss'  ? 'Stop Loss' :
                                        ($reason === 'signal_sell' ? 'Signal Jual' : ucfirst($reason))) }}
                                </span>
                                <span class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($trade->closed_at)->format('d/m H:i') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if($trades->hasPages())
                    <div class="px-4 py-3 border-t border-slate-100">
                        {{ $trades->links() }}
                    </div>
                @endif

            @endif
        </div>

    </div>

</div>
