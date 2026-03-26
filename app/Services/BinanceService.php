<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Market data service.
 * Kline data   : Gate.io (primary) → Binance (fallback)
 * IDR price    : Indodax public ticker
 *
 * Gate.io accessible in Indonesia; Binance.com is geo-blocked by Kominfo.
 */
class BinanceService
{
    private const GATEIO_URL  = 'https://api.gateio.ws/api/v4/spot/candlesticks';
    private const BINANCE_URL = 'https://api.binance.com';
    private const INDODAX_URL = 'https://indodax.com/api';

    /** Binance pair → Indodax IDR pair */
    private array $pairMap = [
        'BTCUSDT'   => 'btc_idr',
        'ETHUSDT'   => 'eth_idr',
        'BNBUSDT'   => 'bnb_idr',
        'SOLUSDT'   => 'sol_idr',
        'XRPUSDT'   => 'xrp_idr',
        'ADAUSDT'   => 'ada_idr',
        'DOGEUSDT'  => 'doge_idr',
        'DOTUSDT'   => 'dot_idr',
        'MATICUSDT' => 'pol_idr',   // MATIC rebranded to POL on Indodax
        'LTCUSDT'   => 'ltc_idr',
        'LINKUSDT'  => 'link_idr',
        'AVAXUSDT'  => 'avax_idr',
        'ATOMUSDT'  => 'atom_idr',
        'UNIUSDT'   => 'uni_idr',
        'XLMUSDT'   => 'xlm_idr',
        'TRXUSDT'   => 'trx_idr',
        'NEARUSDT'  => 'near_idr',
        'ALGOUSDT'  => 'algo_idr',
        'FTMUSDT'   => 'sonic_idr', // Fantom rebranded to Sonic on Indodax
        'SANDUSDT'  => 'sand_idr',
        'MANAUSDT'  => 'mana_idr',
        'AXSUSDT'   => 'axs_idr',
        'AAVEUSDT'  => 'aave_idr',
        'COMPUSDT'  => 'comp_idr',
        'GRTUSDT'   => 'grt_idr',
        'ENJUSDT'   => 'enj_idr',
        'CHZUSDT'   => 'chz_idr',
        'BATUSDT'   => 'bat_idr',
        'ZRXUSDT'   => 'zrx_idr',
        'OMGUSDT'   => 'omg_idr',
        // ICX, QTUM, ZEC, DASH, APT: on Gate.io but not on Indodax → IDR price falls back to USDT×rate
        'XTZUSDT'   => 'xtz_idr',
        'ETCUSDT'   => 'etc_idr',
        // EOS: unavailable on both Gate.io and Indodax → removed
        'VETUSDT'   => 'vet_idr',
        'THETAUSDT' => 'theta_idr',
        'FILUSDT'   => 'fil_idr',
        'HOTUSDT'   => 'hot_idr',
        'WAVESUSDT' => 'waves_idr',
        'XEMUSDT'   => 'xem_idr',
        'SUSHIUSDT' => 'sushi_idr',
        'CRVUSDT'   => 'crv_idr',
        'SNXUSDT'   => 'snx_idr',
        'YFIUSDT'   => 'yfi_idr',
        '1INCHUSDT' => '1inch_idr',
        'KNCUSDT'   => 'knc_idr',
        'IOTAUSDT'  => 'iota_idr',
        'ICPUSDT'   => 'icp_idr',
        'OPUSDT'    => 'op_idr',
        'ARBUSDT'   => 'arb_idr',
        // APT not on Indodax → IDR price falls back to USDT×rate
        'SUIUSDT'   => 'sui_idr',
    ];

    public static function availablePairs(): array
    {
        return [
            'BTCUSDT'   => 'BTC/IDR',
            'ETHUSDT'   => 'ETH/IDR',
            'BNBUSDT'   => 'BNB/IDR',
            'SOLUSDT'   => 'SOL/IDR',
            'XRPUSDT'   => 'XRP/IDR',
            'ADAUSDT'   => 'ADA/IDR',
            'DOGEUSDT'  => 'DOGE/IDR',
            'DOTUSDT'   => 'DOT/IDR',
            'MATICUSDT' => 'MATIC/IDR',
            'LTCUSDT'   => 'LTC/IDR',
            'LINKUSDT'  => 'LINK/IDR',
            'AVAXUSDT'  => 'AVAX/IDR',
            'ATOMUSDT'  => 'ATOM/IDR',
            'UNIUSDT'   => 'UNI/IDR',
            'XLMUSDT'   => 'XLM/IDR',
            'TRXUSDT'   => 'TRX/IDR',
            'NEARUSDT'  => 'NEAR/IDR',
            'ALGOUSDT'  => 'ALGO/IDR',
            'FTMUSDT'   => 'S(FTM)/IDR',
            'SANDUSDT'  => 'SAND/IDR',
            'MANAUSDT'  => 'MANA/IDR',
            'AXSUSDT'   => 'AXS/IDR',
            'AAVEUSDT'  => 'AAVE/IDR',
            'COMPUSDT'  => 'COMP/IDR',
            'GRTUSDT'   => 'GRT/IDR',
            'ENJUSDT'   => 'ENJ/IDR',
            'CHZUSDT'   => 'CHZ/IDR',
            'BATUSDT'   => 'BAT/IDR',
            'ZRXUSDT'   => 'ZRX/IDR',
            'OMGUSDT'   => 'OMG/IDR',
            'ICXUSDT'   => 'ICX/IDR',
            'QTUMUSDT'  => 'QTUM/IDR',
            'XTZUSDT'   => 'XTZ/IDR',
            'ZECUSDT'   => 'ZEC/IDR',
            'DASHUSDT'  => 'DASH/IDR',
            'ETCUSDT'   => 'ETC/IDR',
            // EOS removed — unavailable on Gate.io and Indodax
            'VETUSDT'   => 'VET/IDR',
            'THETAUSDT' => 'THETA/IDR',
            'FILUSDT'   => 'FIL/IDR',
            'HOTUSDT'   => 'HOT/IDR',
            'WAVESUSDT' => 'WAVES/IDR',
            'XEMUSDT'   => 'XEM/IDR',
            'SUSHIUSDT' => 'SUSHI/IDR',
            'CRVUSDT'   => 'CRV/IDR',
            'SNXUSDT'   => 'SNX/IDR',
            'YFIUSDT'   => 'YFI/IDR',
            '1INCHUSDT' => '1INCH/IDR',
            'KNCUSDT'   => 'KNC/IDR',
            'IOTAUSDT'  => 'IOTA/IDR',
            'ICPUSDT'   => 'ICP/IDR',
            'OPUSDT'    => 'OP/IDR',
            'ARBUSDT'   => 'ARB/IDR',
            'APTUSDT'   => 'APT/IDR',
            'SUIUSDT'   => 'SUI/IDR',
        ];
    }

    // ─── HTTP Client ──────────────────────────────────────────────────────────

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::timeout(8);
        if (! env('HTTP_VERIFY_SSL', true)) {
            $client = $client->withoutVerifying();
        }
        return $client;
    }

    // ─── Klines (OHLC) ───────────────────────────────────────────────────────

    /**
     * Pairs renamed on Gate.io (Binance symbol → Gate.io base token).
     * e.g. MATICUSDT uses POL_USDT on Gate.io after Polygon rebranded MATIC → POL.
     */
    private array $gateioRename = [
        'MATICUSDT' => 'POL_USDT',
        'FTMUSDT'   => 'S_USDT',    // Fantom rebranded to Sonic (S)
    ];

    /**
     * Fetch klines from Gate.io (accessible in Indonesia).
     * Returns Binance-compatible format: [timestamp_ms, open, high, low, close, volume]
     */
    public function getKlines(string $symbol, string $interval = '5m', int $limit = 100): array
    {
        return $this->getKlinesFromGateio($symbol, $interval, $limit);
    }

    /**
     * Gate.io format: [timestamp_s, quote_vol, close, high, low, open, base_vol, is_closed]
     * Converted to:   [timestamp_ms, open, high, low, close, volume]
     */
    private function getKlinesFromGateio(string $binancePair, string $interval, int $limit): array
    {
        $upper = strtoupper($binancePair);

        // Use renamed pair if applicable, otherwise derive: BTCUSDT → BTC_USDT
        $gatePair = $this->gateioRename[$upper]
            ?? (substr($upper, 0, -4) . '_USDT');

        // Gate.io doesn't support 3m — use 5m
        $gateInterval = $interval === '3m' ? '5m' : $interval;

        try {
            $response = $this->http()
                ->timeout(8)
                ->get(self::GATEIO_URL, [
                    'currency_pair' => $gatePair,
                    'interval'      => $gateInterval,
                    'limit'         => $limit,
                ]);

            if (! $response->successful()) {
                Log::warning("BinanceService: Gate.io {$gatePair} — HTTP {$response->status()}");
                return [];
            }

            $raw = $response->json();
            if (empty($raw) || ! is_array($raw)) {
                return [];
            }

            // Gate.io: [ts, quote_vol, close, high, low, open, vol, closed]
            // Binance: [ts_ms, open, high, low, close, volume]
            return array_map(fn($k) => [
                (int) $k[0] * 1000,
                (string) $k[5], // open
                (string) $k[3], // high
                (string) $k[4], // low
                (string) $k[2], // close
                (string) $k[6], // volume
            ], $raw);

        } catch (\Throwable $e) {
            Log::warning("BinanceService: Gate.io exception {$gatePair} — {$e->getMessage()}");
            return [];
        }
    }

    // ─── Price ────────────────────────────────────────────────────────────────

    /**
     * Get current IDR price from Indodax public ticker.
     */
    public function getIdrPrice(string $binancePair): float
    {
        $indodaxPair = $this->mapToIndodaxPair($binancePair);

        try {
            $response = $this->http()
                ->timeout(8)
                ->get(self::INDODAX_URL . "/{$indodaxPair}/ticker");

            if ($response->successful()) {
                return (float) ($response->json()['ticker']['last'] ?? 0);
            }
        } catch (\Throwable $e) {
            Log::warning("BinanceService: Indodax ticker failed for {$indodaxPair} — {$e->getMessage()}");
        }

        return 0.0;
    }

    /**
     * Get current IDR price. Indodax ticker is primary.
     * Fallback: last kline close (USDT) × approximate IDR rate.
     */
    public function getCurrentIdrPrice(string $binancePair): float
    {
        $idr = $this->getIdrPrice($binancePair);
        if ($idr > 0) {
            return $idr;
        }

        $klines = $this->getKlines($binancePair, '1m', 2);
        if (! empty($klines)) {
            $usdtPrice = (float) end($klines)[4];
            return $usdtPrice * 16500; // approximate USD/IDR rate
        }

        return 0.0;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function mapToIndodaxPair(string $binancePair): string
    {
        $upper = strtoupper($binancePair);
        return $this->pairMap[$upper]
            ?? strtolower(str_replace('USDT', '_idr', $upper));
    }

    public function getDisplayName(string $binancePair): string
    {
        $upper = strtoupper($binancePair);
        $pairs = self::availablePairs();
        return $pairs[$upper] ?? str_replace('USDT', '/IDR', $upper);
    }

    public function getClosingPrices(array $klines): array
    {
        return array_map(fn($k) => (float) $k[4], $klines);
    }
}
