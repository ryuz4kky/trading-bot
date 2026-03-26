<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CronController;
use App\Livewire\BotSettings;
use App\Livewire\Dashboard;
use App\Livewire\ManualScan;
use App\Livewire\ProfitHistory;
use Illuminate\Support\Facades\Route;

// ── Auth ──────────────────────────────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ── Redirect root ─────────────────────────────────────────────────────────────
Route::get('/', fn () => redirect()->route('dashboard'));

// ── Web Cron Endpoint (for hosting without CLI access) ────────────────────────
// Set CRON_SECRET in .env, then call: GET /cron/run/{secret}
Route::get('/cron/run/{key}', [CronController::class, 'run'])->name('cron.run');

// ── Protected Routes (admin only) ─────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/manual-scan', ManualScan::class)->name('manual-scan');
    Route::get('/settings', BotSettings::class)->name('settings');
    Route::get('/profit', ProfitHistory::class)->name('profit');
});
