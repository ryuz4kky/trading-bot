<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Services\BotService;
use Illuminate\Console\Command;

class BotRun extends Command
{
    protected $signature   = 'bot:run';
    protected $description = 'Jalankan bot trading untuk semua bot yang aktif (status=running)';

    public function handle(BotService $service): int
    {
        $bots = Bot::where('status', 'running')->get();

        if ($bots->isEmpty()) {
            $this->warn('Tidak ada bot yang aktif.');
            return Command::SUCCESS;
        }

        foreach ($bots as $bot) {
            try {
                $this->info("Menjalankan bot #{$bot->id} ({$bot->name})...");
                $service->run($bot);
                $this->info('✓ Selesai.');
            } catch (\Throwable $e) {
                $this->error("✗ Bot #{$bot->id} error: {$e->getMessage()}");
            }
        }

        return Command::SUCCESS;
    }
}
