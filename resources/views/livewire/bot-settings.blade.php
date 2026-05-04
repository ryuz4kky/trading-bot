<div class="space-y-6 max-w-2xl">

    {{-- Header --}}
    <div>
        <h1 class="text-xl font-bold text-slate-900">Settings</h1>
        <p class="text-xs text-slate-400 mt-0.5">Konfigurasi bot trading</p>
    </div>

    {{-- Feedback --}}
    @if($successMessage)
        <div class="flex items-center gap-2.5 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            {{ $successMessage }}
        </div>
    @endif

    @if($errorMessage)
        <div class="flex items-center gap-2.5 p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ $errorMessage }}
        </div>
    @endif

    {{-- ── Section: Umum ── --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <h2 class="text-sm font-semibold text-slate-800">Pengaturan Umum</h2>
        </div>

        <form wire:submit.prevent="saveSettings" class="px-6 py-5 space-y-5">

            {{-- Bot Name --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nama Bot</label>
                <input type="text" wire:model="botName"
                       class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-900 bg-white
                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                       placeholder="Main Bot">
                @error('botName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Mode --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-2">Mode Trading</label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl border-2 cursor-pointer transition
                        {{ $mode === 'simulation' ? 'border-amber-400 bg-amber-50' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                        <input type="radio" wire:model.live="mode" value="simulation" class="accent-amber-500">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Simulasi</p>
                            <p class="text-[11px] text-slate-400">Saldo virtual</p>
                        </div>
                    </label>
                    <label class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl border-2 cursor-pointer transition
                        {{ $mode === 'real' ? 'border-blue-400 bg-blue-50' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                        <input type="radio" wire:model.live="mode" value="real" class="accent-blue-600">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Real</p>
                            <p class="text-[11px] text-slate-400">Eksekusi ke Indodax</p>
                        </div>
                    </label>
                </div>
                @if($mode === 'real')
                    <p class="mt-2 text-xs text-amber-600 bg-amber-50 border border-amber-200 px-3 py-2 rounded-lg">
                        ⚠ Mode REAL akan mengeksekusi order nyata ke Indodax. Pastikan API Key sudah dikonfigurasi.
                    </p>
                @endif
            </div>

            {{-- Pairs Selector --}}
            <div x-data="{ search: '' }">
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-slate-600">
                        Pair Aktif
                        <span class="ml-1.5 px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-[10px] font-bold">
                            {{ count($selectedPairs) }} dipilih
                        </span>
                    </label>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                wire:click="selectAllPairs"
                                class="text-[10px] font-semibold text-blue-600 hover:text-blue-800 transition">
                            Pilih Semua
                        </button>
                        <span class="text-slate-300">·</span>
                        <button type="button"
                                wire:click="clearAllPairs"
                                class="text-[10px] font-semibold text-slate-400 hover:text-slate-600 transition">
                            Hapus Semua
                        </button>
                    </div>
                </div>

                {{-- Search --}}
                <div class="relative mb-2">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" x-model="search" placeholder="Cari pair... (BTC, ETH, SOL...)"
                           class="w-full border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-sm text-slate-800 bg-white
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>

                {{-- Grid --}}
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <div class="max-h-64 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 divide-y divide-slate-100">
                        @php $availablePairs = \App\Services\BinanceService::availablePairs(); @endphp
                        @foreach($availablePairs as $symbol => $display)
                            <label
                                x-show="search === '' || '{{ strtolower($symbol) }}'.includes(search.toLowerCase()) || '{{ strtolower($display) }}'.includes(search.toLowerCase())"
                                class="flex items-center gap-2.5 px-3 py-2.5 cursor-pointer transition hover:bg-slate-50
                                    {{ in_array($symbol, $selectedPairs) ? 'bg-blue-50' : '' }}">
                                <input type="checkbox"
                                       wire:model="selectedPairs"
                                       value="{{ $symbol }}"
                                       class="w-3.5 h-3.5 accent-blue-600 rounded shrink-0">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate-800 truncate">{{ $display }}</p>
                                    <p class="text-[10px] text-slate-400 truncate">{{ $symbol }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
                @error('selectedPairs') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <p class="mt-1 text-[11px] text-slate-400">Pair Binance untuk analisis. Eksekusi otomatis ke IDR di Indodax.</p>
            </div>

            {{-- Full Balance System --}}
            <div class="p-4 rounded-xl bg-blue-50 border border-blue-100">
                <div class="flex items-start gap-3 mb-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white shrink-0 mt-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-blue-900">Full Balance System</p>
                        <p class="text-xs text-blue-600 mt-0.5">
                            Modal dibagi rata ke semua posisi aktif.
                        </p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-semibold text-blue-700 mb-1">Max Posisi Bersamaan</label>
                        <input type="number" wire:model.live="maxPositions" min="1" max="10"
                               class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm font-bold text-blue-900 bg-white
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        @error('maxPositions') <p class="mt-1 text-[10px] text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-col justify-center px-3 py-2 rounded-lg bg-white border border-blue-100">
                        <p class="text-[10px] text-blue-500">Alokasi per posisi</p>
                        @php
                            $simBal = (int) $simulationBalance;
                            $maxPos = max(1, (int) $maxPositions);
                            $perPos = $simBal > 0 ? round($simBal / $maxPos) : 0;
                        @endphp
                        <p class="text-sm font-bold text-blue-700 mt-0.5">
                            Rp {{ number_format($perPos, 0, ',', '.') }}
                        </p>
                        <p class="text-[10px] text-blue-400">Total modal dibagi {{ $maxPositions }} posisi</p>
                    </div>
                </div>
            </div>

            {{-- Money Management --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-2">Manajemen Risiko</label>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1">Stop Loss (%)</label>
                        <input type="number" step="0.1" wire:model="stopLossPercent"
                               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 bg-white
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        @error('stopLossPercent') <p class="mt-1 text-[10px] text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1">Take Profit (%)</label>
                        <input type="number" step="0.1" wire:model="takeProfitPercent"
                               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 bg-white
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        @error('takeProfitPercent') <p class="mt-1 text-[10px] text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1">
                            Max Loss Harian (%)
                            <span class="text-orange-400">— bot berhenti jika tercapai</span>
                        </label>
                        <input type="number" step="0.5" wire:model="maxDailyLossPercent"
                               class="w-full border border-orange-200 rounded-lg px-3 py-2 text-sm text-slate-900 bg-orange-50
                                      focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent">
                        @error('maxDailyLossPercent') <p class="mt-1 text-[10px] text-red-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Trailing Stop Loss --}}
                <div class="mt-3 p-3 rounded-xl border border-slate-200 bg-slate-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-700">Trailing Stop Loss</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">SL otomatis naik mengikuti harga, mengunci profit saat posisi untung.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer ml-4 shrink-0">
                            <input type="checkbox" wire:model="trailingSlEnabled" class="sr-only peer">
                            <div class="w-9 h-5 bg-slate-200 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer
                                        peer-checked:after:translate-x-full peer-checked:bg-blue-600
                                        after:content-[''] after:absolute after:top-0.5 after:left-0.5
                                        after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all"></div>
                        </label>
                    </div>
                    @if($trailingSlEnabled)
                    <div class="mt-3">
                        <label class="block text-[10px] text-slate-400 mb-1">Trailing Distance (%) <span class="text-blue-400">(rec: 1.5%)</span></label>
                        <input type="number" step="0.1" wire:model="trailingSlPercent"
                               class="w-40 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 bg-white
                                      focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-[10px] text-slate-400 mt-1">SL akan bergerak ke <strong>peak_price × (1 - {{ $trailingSlPercent }}%)</strong> saat harga naik.</p>
                        @error('trailingSlPercent') <p class="mt-1 text-[10px] text-red-500">{{ $message }}</p> @enderror
                    </div>
                    @endif
                </div>

                {{-- Cooldown + Volume --}}
                <div class="grid grid-cols-2 gap-3 mt-3">
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1">
                            Cooldown setelah SL (candle)
                            <span class="text-blue-400">— rec: 3</span>
                        </label>
                        <input type="number" wire:model="cooldownCandles"
                               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 bg-white
                                      focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-[10px] text-slate-400 mt-1">0 = tidak ada cooldown</p>
                        @error('cooldownCandles') <p class="mt-1 text-[10px] text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1">
                            Min Volume Ratio
                            <span class="text-blue-400">— rec: 1.2</span>
                        </label>
                        <input type="number" step="0.1" wire:model="volumeMinRatio"
                               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 bg-white
                                      focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-[10px] text-slate-400 mt-1">Volume candle / rata-rata 20 candle. 1.2 = 20% di atas rata-rata.</p>
                        @error('volumeMinRatio') <p class="mt-1 text-[10px] text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1">
                            RSI Buy Threshold
                            <span class="text-blue-400">— rec adaptive: 38</span>
                        </label>
                        <input type="number" wire:model="rsiBuyThreshold"
                               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 bg-white
                                      focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-[10px] text-slate-400 mt-1">BUY saat RSI di bawah nilai ini. Lebih tinggi = lebih banyak sinyal.</p>
                        @error('rsiBuyThreshold') <p class="mt-1 text-[10px] text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1">
                            ADX Trend Threshold
                            <span class="text-blue-400">— rec: 25</span>
                        </label>
                        <input type="number" wire:model="adxTrendThreshold"
                               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 bg-white
                                      focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-[10px] text-slate-400 mt-1">ADX ≥ nilai ini = trending market. Dipakai adaptive untuk pilih strategi.</p>
                        @error('adxTrendThreshold') <p class="mt-1 text-[10px] text-red-500">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Strategy --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-2">Strategi Trading</label>
                <div class="grid grid-cols-1 gap-2">
                    @foreach(\App\Models\BotSetting::STRATEGIES as $value => $label)
                        @php
                            $descriptions = [
                                'adaptive'           => 'Auto-detect per pair: trending→EMA Crossover | sideways→RSI Mean Reversion | squeeze→BB Squeeze.',
                                'ema_crossover'      => 'EMA20 cross EMA50 + RSI filter. Bagus untuk trending market.',
                                'rsi_mean_reversion' => 'Beli saat oversold (RSI<35) di lower BB, jual saat overbought. Win rate tinggi di sideways market.',
                                'bb_squeeze'         => 'Deteksi breakout setelah BB menyempit. Cocok untuk volatilitas rendah yang akan meledak.',
                            ];
                        @endphp
                        <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition
                            {{ $strategy === $value
                                ? 'border-blue-500 bg-blue-50'
                                : 'border-slate-200 bg-white hover:border-slate-300' }}">
                            <input type="radio" wire:model="strategy" value="{{ $value }}"
                                   class="mt-0.5 accent-blue-600 shrink-0">
                            <div>
                                <p class="text-sm font-semibold {{ $strategy === $value ? 'text-blue-700' : 'text-slate-800' }}">
                                    {{ $label }}
                                </p>
                                <p class="text-[11px] text-slate-400 mt-0.5">{{ $descriptions[$value] }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Indicators --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-semibold text-slate-600">Indikator Teknikal</label>
                    <button type="button" wire:click="applyRecommended"
                            class="text-[11px] font-semibold text-blue-600 hover:text-blue-700 underline underline-offset-2">
                        Terapkan Rekomendasi
                    </button>
                </div>
                <div class="grid grid-cols-3 gap-3">

                    {{-- EMA: untuk ema_crossover dan adaptive --}}
                    @if($strategy === 'ema_crossover' || $strategy === 'adaptive')
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1">EMA Fast <span class="text-blue-400">(rec: 20)</span></label>
                        <input type="number" wire:model="emaFast"
                               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 bg-white
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        @error('emaFast') <p class="mt-1 text-[10px] text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1">EMA Slow <span class="text-blue-400">(rec: 50)</span></label>
                        <input type="number" wire:model="emaSlow"
                               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 bg-white
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        @error('emaSlow') <p class="mt-1 text-[10px] text-red-500">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    {{-- BB Period: untuk rsi_mean_reversion dan bb_squeeze --}}
                    @if(in_array($strategy, ['rsi_mean_reversion', 'bb_squeeze', 'adaptive']))
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1">BB Period <span class="text-blue-400">(rec: 20)</span></label>
                        <input type="number" wire:model="bbPeriod"
                               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 bg-white
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        @error('bbPeriod') <p class="mt-1 text-[10px] text-red-500">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1">RSI Period <span class="text-blue-400">(rec: 14)</span></label>
                        <input type="number" wire:model="rsiPeriod"
                               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 bg-white
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        @error('rsiPeriod') <p class="mt-1 text-[10px] text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1">
                            Interval
                            @if($strategy === 'ema_crossover') <span class="text-blue-400">(rec: 15m)</span>
                            @elseif($strategy === 'rsi_mean_reversion') <span class="text-blue-400">(rec: 15m)</span>
                            @else <span class="text-blue-400">(rec: 1h)</span>
                            @endif
                        </label>
                        <select wire:model="klineInterval"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 bg-white
                                       focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            @foreach(['1m' => '1 Menit', '3m' => '3 Menit', '5m' => '5 Menit', '15m' => '15 Menit', '30m' => '30 Menit', '1h' => '1 Jam'] as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="pt-1">
                <button type="submit" wire:loading.attr="disabled"
                        class="bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold
                               px-6 py-2.5 rounded-xl text-sm transition shadow-sm">
                    <span wire:loading.remove wire:target="saveSettings">Simpan Settings</span>
                    <span wire:loading wire:target="saveSettings">Menyimpan...</span>
                </button>
            </div>
        </form>
    </div>

    {{-- ── Section: Simulasi ── --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <h2 class="text-sm font-semibold text-slate-800">Saldo Simulasi</h2>
        </div>
        <div class="px-6 py-5 space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Saldo Awal IDR</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-400 font-medium">Rp</span>
                    <input type="number" wire:model="simulationBalance"
                           class="w-full border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-sm text-slate-900 bg-white
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="10000000">
                </div>
                @error('simulationBalance') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <button wire:click="resetSimulationBalance" wire:loading.attr="disabled"
                    wire:confirm="Reset saldo simulasi ke awal? Posisi crypto (BTC, ETH, dll) akan menjadi 0."
                    class="inline-flex items-center gap-2 bg-amber-50 hover:bg-amber-100 disabled:opacity-60
                           border border-amber-200 text-amber-700 font-semibold px-4 py-2.5 rounded-xl text-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span wire:loading.remove wire:target="resetSimulationBalance">Reset Saldo</span>
                <span wire:loading wire:target="resetSimulationBalance">Mereset...</span>
            </button>
        </div>
    </div>

    {{-- ── Section: API Keys ── --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-slate-800">Indodax API Keys</h2>
                <p class="text-[11px] text-slate-400 mt-0.5">Dienkripsi sebelum disimpan ke database</p>
            </div>
            <button wire:click="toggleApiForm"
                    class="text-xs font-semibold px-3 py-1.5 rounded-lg transition
                        {{ $showApiForm ? 'bg-slate-100 text-slate-600 hover:bg-slate-200' : 'bg-blue-50 text-blue-600 hover:bg-blue-100' }}">
                {{ $showApiForm ? '✕ Batal' : '✎ Ubah Keys' }}
            </button>
        </div>

        <div class="px-6 py-5">
            @if(! $showApiForm)
                <div class="flex items-center gap-3">
                    @php $hasKey = $bot->hasApiKeys(); @endphp
                    @if($hasKey)
                        <div class="w-8 h-8 bg-emerald-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">API Key tersimpan</p>
                            <p class="text-xs text-slate-400">Terenkripsi dengan Laravel Crypt</p>
                        </div>
                    @else
                        <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.962-.833-2.732 0L4.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-amber-700">Belum dikonfigurasi</p>
                            <p class="text-xs text-slate-400">Mode REAL membutuhkan API Key Indodax</p>
                        </div>
                    @endif
                </div>

            @else
                <form wire:submit.prevent="saveApiKeys" class="space-y-4">
                    <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-700">
                        ⚠ Pastikan API Key Indodax sudah diaktifkan dan memiliki permission <strong>Trade</strong>.
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">API Key</label>
                        <input type="text" wire:model="newApiKey"
                               class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-900 font-mono bg-white
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Paste API Key di sini" autocomplete="off">
                        @error('newApiKey') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Secret Key</label>
                        <input type="password" wire:model="newApiSecret"
                               class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-900 font-mono bg-white
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Paste Secret Key di sini" autocomplete="off">
                        @error('newApiSecret') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit" wire:loading.attr="disabled"
                                class="bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold
                                       px-5 py-2.5 rounded-xl text-sm transition">
                            <span wire:loading.remove wire:target="saveApiKeys">Simpan & Enkripsi</span>
                            <span wire:loading wire:target="saveApiKeys">Menyimpan...</span>
                        </button>
                        <button type="button" wire:click="toggleApiForm"
                                class="px-5 py-2.5 rounded-xl text-sm font-medium text-slate-600
                                       bg-slate-100 hover:bg-slate-200 transition">
                            Batal
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- ── Section: Sync Saldo ── --}}
    @if($bot->hasApiKeys())
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <h2 class="text-sm font-semibold text-slate-800">Saldo Indodax</h2>
            <p class="text-[11px] text-slate-400 mt-0.5">Ambil saldo terkini dari akun Indodax kamu</p>
        </div>
        <div class="px-6 py-5">

            @if($syncError)
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600">
                    {{ $syncError }}
                </div>
            @endif

            @if(!empty($indodaxBalances))
                <div class="mb-4 grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach($indodaxBalances as $currency => $amount)
                        @php
                            $amt    = (float) $amount;
                            $locked = (float) ($bot->balances->where('currency', strtoupper($currency))->first()?->locked ?? 0);
                        @endphp
                        @if($amt > 0 || $locked > 0)
                        <div class="bg-slate-50 rounded-xl px-4 py-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-600 uppercase">{{ $currency }}</span>
                                <span class="text-sm font-bold text-slate-800">
                                    @if(strtolower($currency) === 'idr')
                                        Rp {{ number_format($amt, 0, ',', '.') }}
                                    @else
                                        {{ number_format($amt, 8, '.', '') + 0 }}
                                    @endif
                                </span>
                            </div>
                            @if($locked > 0)
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-[10px] text-orange-400">hold</span>
                                    <span class="text-[11px] font-semibold text-orange-500">
                                        @if(strtolower($currency) === 'idr')
                                            Rp {{ number_format($locked, 0, ',', '.') }}
                                        @else
                                            {{ number_format($locked, 8, '.', '') + 0 }}
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-3">
                <button wire:click="syncBalanceFromIndodax" wire:loading.attr="disabled"
                        class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60
                               text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition">
                    <svg wire:loading.remove wire:target="syncBalanceFromIndodax" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <svg wire:loading wire:target="syncBalanceFromIndodax" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <span wire:loading.remove wire:target="syncBalanceFromIndodax">Ambil Saldo dari Indodax</span>
                    <span wire:loading wire:target="syncBalanceFromIndodax">Mengambil...</span>
                </button>

                @if(!empty($indodaxBalances))
                <button wire:click="importHoldingsAsTrades"
                        wire:loading.attr="disabled"
                        wire:confirm="Import semua crypto holdings sebagai posisi terbuka? Entry price akan menggunakan harga pasar saat ini."
                        class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-60
                               text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition">
                    <svg wire:loading.remove wire:target="importHoldingsAsTrades" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <svg wire:loading wire:target="importHoldingsAsTrades" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <span wire:loading.remove wire:target="importHoldingsAsTrades">Import sebagai Posisi Terbuka</span>
                    <span wire:loading wire:target="importHoldingsAsTrades">Mengimport...</span>
                </button>
                @endif

                @if($syncSuccess)
                    <span class="text-sm text-emerald-600 font-medium">✓ Saldo berhasil disinkronkan</span>
                @endif
            </div>

            @if(!empty($indodaxBalances))
                <p class="mt-3 text-xs text-slate-400">
                    Klik "Ambil Saldo" untuk set modal awal bot berdasarkan saldo IDR di Indodax.
                    Saldo IDR akan dijadikan <strong>simulation_balance</strong> jika mode simulasi,
                    atau dipakai sebagai referensi modal di mode REAL.
                </p>
            @endif

        </div>
    </div>
    @endif

    {{-- ── Section: Reset Data ── --}}
    <div class="bg-white rounded-2xl border border-red-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-red-100 bg-red-50/50">
            <h2 class="text-sm font-semibold text-red-700">Danger Zone</h2>
            <p class="text-[11px] text-red-400 mt-0.5">Tindakan ini tidak bisa dibatalkan</p>
        </div>
        <div class="px-6 py-5 flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-slate-700">Reset Semua Transaksi</p>
                <p class="text-xs text-slate-400 mt-0.5">Hapus semua trade dan log. Saldo tidak akan terpengaruh.</p>
            </div>
            <button wire:click="resetAllData"
                    wire:loading.attr="disabled"
                    wire:confirm="Yakin ingin menghapus semua trade dan log? Tindakan ini tidak bisa dibatalkan."
                    class="flex-shrink-0 flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold
                           bg-red-600 hover:bg-red-700 text-white disabled:opacity-50 transition">
                <svg wire:loading.remove wire:target="resetAllData" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                <svg wire:loading wire:target="resetAllData" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                <span wire:loading.remove wire:target="resetAllData">Reset Data</span>
                <span wire:loading wire:target="resetAllData">Menghapus...</span>
            </button>
        </div>
    </div>

</div>
