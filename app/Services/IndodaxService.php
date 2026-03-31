<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndodaxService
{
    private const BASE_URL = 'https://indodax.com/tapi';

    private string $apiKey;
    private string $apiSecret;

    public function __construct(string $apiKey = '', string $apiSecret = '')
    {
        $this->apiKey    = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    /**
     * Get account balances from Indodax.
     * Returns associative array: ['idr' => amount, 'btc' => amount, ...]
     */
    public function getBalance(): array
    {
        $response = $this->privateRequest('getInfo');

        if (! $response || ! isset($response['return']['balance'])) {
            return [];
        }

        return $response['return']['balance'];
    }

    /**
     * Get full balance info including balance_hold.
     * Returns ['balance' => [...], 'balance_hold' => [...]]
     */
    public function getFullBalanceInfo(): array
    {
        $response = $this->privateRequest('getInfo');

        if (! $response || ! isset($response['return']['balance'])) {
            return ['balance' => [], 'balance_hold' => []];
        }

        return [
            'balance'      => $response['return']['balance'] ?? [],
            'balance_hold' => $response['return']['balance_hold'] ?? [],
        ];
    }

    /**
     * Place a buy order on Indodax.
     *
     * @param string $pair      e.g. btc_idr
     * @param float  $amountIdr IDR amount to spend
     * @param float  $price     Limit price in IDR
     */
    public function buy(string $pair, float $amountIdr, float $price): array
    {
        [$crypto] = explode('_', $pair);

        return $this->privateRequest('trade', [
            'pair'        => $pair,
            'type'        => 'buy',
            'price'       => (int) $price,
            $crypto       => 0,               // Indodax uses IDR amount for buy
            'idr'         => (int) $amountIdr,
        ]) ?? [];
    }

    /**
     * Place a sell order on Indodax.
     *
     * @param string $pair       e.g. btc_idr
     * @param float  $quantity   Crypto amount to sell
     * @param float  $price      Limit price in IDR
     */
    public function sell(string $pair, float $quantity, float $price): array
    {
        [$crypto] = explode('_', $pair);

        return $this->privateRequest('trade', [
            'pair'   => $pair,
            'type'   => 'sell',
            'price'  => (int) $price,
            $crypto  => number_format($quantity, 8, '.', ''),
        ]) ?? [];
    }

    /**
     * Get open orders.
     */
    public function getOpenOrders(string $pair): array
    {
        $response = $this->privateRequest('openOrders', ['pair' => $pair]);

        return $response['return']['orders'] ?? [];
    }

    /**
     * Cancel an order.
     */
    public function cancelOrder(string $pair, int $orderId, string $type): array
    {
        return $this->privateRequest('cancelOrder', [
            'pair'     => $pair,
            'order_id' => $orderId,
            'type'     => $type,
        ]) ?? [];
    }

    /**
     * Send a signed private API request to Indodax.
     */
    private function privateRequest(string $method, array $params = []): ?array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::warning('IndodaxService: API key/secret not configured.');
            return null;
        }

        $nonce    = (int) (microtime(true) * 1000);
        $postData = array_merge([
            'method' => $method,
            'nonce'  => $nonce,
        ], $params);

        $queryString = http_build_query($postData);
        $signature   = hash_hmac('sha512', $queryString, $this->apiSecret);

        try {
            $response = Http::retry(3, 1000)
                ->timeout(20)
                ->withHeaders([
                    'Key'  => $this->apiKey,
                    'Sign' => $signature,
                ])
                ->asForm()
                ->post(self::BASE_URL, $postData);

            if ($response->failed()) {
                Log::warning("IndodaxService: request failed for {$method}", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();

            if (($data['success'] ?? 0) !== 1) {
                Log::warning("IndodaxService: API error for {$method}", [
                    'error' => $data['error'] ?? 'unknown',
                ]);
                // Return error data so callers can read the message
                return $data;
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error("IndodaxService: exception on {$method}: {$e->getMessage()}");
            return null;
        }
    }
}
