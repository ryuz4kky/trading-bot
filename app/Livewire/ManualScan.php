<?php

namespace App\Livewire;

use App\Models\Bot;
use App\Services\BotService;
use Livewire\Component;

class ManualScan extends Component
{
    public Bot $bot;

    public bool   $scanning   = false;
    public array  $results    = [];
    public string $error      = '';
    public string $scannedAt  = '';

    public function mount(): void
    {
        $this->bot = Bot::with('settings')->firstOrFail();
    }

    public function scan(): void
    {
        $this->scanning  = true;
        $this->results   = [];
        $this->error     = '';
        $this->scannedAt = '';

        // Reload bot with settings (Livewire rehydrates without relations)
        $this->bot = Bot::with('settings')->find($this->bot->id);

        try {
            /** @var BotService $service */
            $service       = app(BotService::class);
            $this->results = $service->scanAll($this->bot);

            if (empty($this->results)) {
                $this->error = 'Tidak ada data. Pastikan pair sudah dikonfigurasi di Settings.';
            }

            $this->scannedAt = now()->format('d M Y, H:i:s');
        } catch (\Throwable $e) {
            $this->error = 'Scan gagal: ' . $e->getMessage();
        } finally {
            $this->scanning = false;
        }
    }

    public function render()
    {
        return view('livewire.manual-scan');
    }
}
