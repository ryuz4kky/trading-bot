<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Services\BotService;
use Illuminate\Http\Request;

class CronController extends Controller
{
    public function run(Request $request, string $key)
    {
        $secret = config('app.cron_secret');

        if (! $secret || $key !== $secret) {
            abort(403, 'Unauthorized');
        }

        $bots    = Bot::where('status', 'running')->get();
        $service = app(BotService::class);
        $count   = 0;

        foreach ($bots as $bot) {
            try {
                $service->run($bot);
                $count++;
            } catch (\Throwable $e) {
                \Log::error("CronController: bot #{$bot->id} error — {$e->getMessage()}");
            }
        }

        return response()->json([
            'ok'        => true,
            'bots_ran'  => $count,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
