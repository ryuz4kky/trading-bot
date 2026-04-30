<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'CryptoBot') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>[x-cloak]{display:none!important}</style>
    @livewireStyles
</head>
<body class="h-full bg-slate-50" x-data="{ mob: false }">

<div class="min-h-screen flex">

    {{-- ══════════════════════════════════════════════════════════
         SIDEBAR DESKTOP
    ══════════════════════════════════════════════════════════ --}}
    <aside class="hidden lg:flex lg:flex-col lg:w-64 lg:fixed lg:inset-y-0 bg-white border-r border-slate-200 shadow-sm">

        {{-- Logo --}}
        <div class="flex items-center gap-3 px-5 h-16 border-b border-slate-100 shrink-0">
            <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white font-bold text-base shadow-sm shadow-blue-200 shrink-0">₿</div>
            <div class="leading-tight">
                <p class="font-bold text-slate-900 text-sm">CryptoBot</p>
                <p class="text-[11px] text-slate-400">Auto Trading System</p>
                <p class="text-[10px] text-slate-400">v{{ config('app.version') }}</p>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 px-3 py-5 space-y-1 overflow-y-auto">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 px-3 pb-2">Menu</p>

            @php
                $navActive   = 'flex items-center gap-3 w-full px-3 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 text-white shadow-sm shadow-blue-200 transition-all';
                $navInactive = 'flex items-center gap-3 w-full px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-all';
            @endphp

            <a href="{{ route('dashboard') }}"
               class="{{ request()->routeIs('dashboard') ? $navActive : $navInactive }}">
                <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>Dashboard</span>
            </a>

            <a href="{{ route('manual-scan') }}"
               class="{{ request()->routeIs('manual-scan') ? $navActive : $navInactive }}">
                <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <span>Market Scan</span>
            </a>

            <a href="{{ route('profit') }}"
               class="{{ request()->routeIs('profit') ? $navActive : $navInactive }}">
                <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Profit</span>
            </a>

            <a href="{{ route('settings') }}"
               class="{{ request()->routeIs('settings') ? $navActive : $navInactive }}">
                <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span>Settings</span>
            </a>
        </nav>

        {{-- Bot Status --}}
        <div class="mx-3 mb-3 p-3 rounded-xl bg-slate-50 border border-slate-100">
            @php
                $botStatus = \App\Models\Bot::value('status');
                $botMode   = \App\Models\Bot::value('mode');
            @endphp
            <div class="flex items-center gap-2.5">
                <div class="w-2 h-2 rounded-full shrink-0
                    {{ $botStatus === 'running' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300' }}"></div>
                <span class="text-xs font-semibold text-slate-700 flex-1">
                    Bot {{ $botStatus === 'running' ? 'Aktif' : 'Berhenti' }}
                </span>
                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold
                    {{ $botMode === 'simulation' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">
                    {{ $botMode === 'simulation' ? 'SIM' : 'REAL' }}
                </span>
            </div>
        </div>

        {{-- User --}}
        <div class="px-3 py-3 border-t border-slate-100">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-blue-700
                            flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm">
                    {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-slate-800 truncate">{{ auth()->user()->name ?? 'Admin' }}</p>
                    <p class="text-[10px] text-slate-400 truncate">{{ auth()->user()->email ?? '' }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Logout"
                            class="w-7 h-7 flex items-center justify-center text-slate-400
                                   hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ══════════════════════════════════════════════════════════
         MOBILE HEADER
    ══════════════════════════════════════════════════════════ --}}
    <div class="lg:hidden fixed top-0 inset-x-0 z-40 h-14 bg-white border-b border-slate-200 flex items-center gap-3 px-4 shadow-sm">
        <button @click="mob = true"
                class="w-9 h-9 flex items-center justify-center text-slate-600 hover:bg-slate-100 rounded-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold text-sm shrink-0">₿</div>
            <div class="leading-tight">
                <p class="font-bold text-slate-900 text-sm">CryptoBot</p>
                <p class="text-[10px] text-slate-400">v{{ config('app.version') }}</p>
            </div>
        </div>
    </div>

    {{-- Mobile Sidebar --}}
    <div x-cloak x-show="mob" class="lg:hidden fixed inset-0 z-50 flex">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="mob = false"></div>
        <div class="relative flex flex-col w-64 bg-white shadow-2xl">
            <div class="flex items-center justify-between gap-2 px-4 h-14 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold text-sm">₿</div>
                    <div class="leading-tight">
                        <p class="font-bold text-slate-900 text-sm">CryptoBot</p>
                        <p class="text-[10px] text-slate-400">v{{ config('app.version') }}</p>
                    </div>
                </div>
                <button @click="mob = false"
                        class="w-7 h-7 flex items-center justify-center text-slate-500 hover:bg-slate-100 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1" @click="mob = false">
                @php
                    $mActive   = 'flex items-center gap-3 w-full px-3 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 text-white';
                    $mInactive = 'flex items-center gap-3 w-full px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-100';
                @endphp
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? $mActive : $mInactive }}">
                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Dashboard
                </a>
                <a href="{{ route('manual-scan') }}" class="{{ request()->routeIs('manual-scan') ? $mActive : $mInactive }}">
                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Market Scan
                </a>
                <a href="{{ route('profit') }}" class="{{ request()->routeIs('profit') ? $mActive : $mInactive }}">
                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Profit
                </a>
                <a href="{{ route('settings') }}" class="{{ request()->routeIs('settings') ? $mActive : $mInactive }}">
                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Settings
                </a>
            </nav>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         MAIN CONTENT
    ══════════════════════════════════════════════════════════ --}}
    <div class="flex-1 lg:pl-64 flex flex-col min-h-screen">
        <div class="lg:hidden h-14 shrink-0"></div>
        <main class="flex-1 p-5 lg:p-7 max-w-6xl w-full mx-auto">
            {{ $slot }}
        </main>
    </div>

</div>

@livewireScripts
</body>
</html>
