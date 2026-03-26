<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Services\BotService;
use Illuminate\Console\Command;

class RunBotCommand extends Command
{
    protected $signature   = 'bot:run {--bot-id= : Specific bot ID to run}';
    protected $description = 'Run the trading bot — scan market, evaluate signals, and execute trades.';

    public function handle(BotService $botService): int
    {
        $query = Bot::with(['settings', 'balances']);

        if ($botId = $this->option('bot-id')) {
            $query->where('id', $botId);
        }

        $bots = $query->where('status', 'running')->get();

        if ($bots->isEmpty()) {
            $this->line('<fg=yellow>No running bots found.</>');
            return self::SUCCESS;
        }

        foreach ($bots as $bot) {
            $this->line("<fg=cyan>Running bot: {$bot->name} (ID: {$bot->id}) | Mode: {$bot->mode}</>");

            try {
                $botService->run($bot);
                $this->line("<fg=green>✓ Bot {$bot->name} run completed.</>");
            } catch (\Throwable $e) {
                $this->error("✗ Bot {$bot->name} error: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
