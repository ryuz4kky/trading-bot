<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Crypt;

class Bot extends Model
{
    protected $fillable = [
        'name',
        'status',
        'mode',
        'indodax_api_key',
        'indodax_api_secret',
        'simulation_balance',
    ];

    protected $casts = [
        'simulation_balance' => 'decimal:2',
    ];

    public function settings(): HasOne
    {
        return $this->hasOne(BotSetting::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(Balance::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BotLog::class);
    }

    public function openTrades(): HasMany
    {
        return $this->hasMany(Trade::class)->where('status', 'open');
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isSimulation(): bool
    {
        return $this->mode === 'simulation';
    }

    public function setIndodaxApiKeyAttribute(?string $value): void
    {
        $this->attributes['indodax_api_key'] = ($value && trim($value) !== '') ? Crypt::encryptString($value) : null;
    }

    public function getIndodaxApiKeyAttribute(?string $value): ?string
    {
        if (! $value) return null;
        try { return Crypt::decryptString($value); } catch (\Throwable) { return null; }
    }

    public function setIndodaxApiSecretAttribute(?string $value): void
    {
        $this->attributes['indodax_api_secret'] = ($value && trim($value) !== '') ? Crypt::encryptString($value) : null;
    }

    public function getIndodaxApiSecretAttribute(?string $value): ?string
    {
        if (! $value) return null;
        try { return Crypt::decryptString($value); } catch (\Throwable) { return null; }
    }

    public function hasApiKeys(): bool
    {
        return ! empty($this->getAttributes()['indodax_api_key']);
    }

    public function getTodayProfitAttribute(): float
    {
        return (float) $this->trades()
            ->where('status', 'closed')
            ->whereDate('closed_at', today())
            ->sum('profit_loss');
    }
}
