<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to Chapa's REST API v2 (https://docs.chapa.global/docs/v2). The chapa/chapa-laravel package is
 * not used because it does not support Laravel 13.
 *
 * v2 differences from v1 that matter here:
 *  - POST /payments/hosted replaces POST /transaction/initialize, and needs an Idempotency-Key header.
 *  - GET /payments/{reference}/verify replaces GET /transaction/verify/{tx_ref}.
 *  - Amounts are integer cents (10000 = 100.00 ETB), and verify reports data.status in UPPERCASE.
 *
 * Both calls return Chapa's answer as an array, or null when Chapa could not be reached or had an
 * error on its side. null means "unknown, try again later", never "payment failed".
 */
class ChapaGateway
{
    public function configured(): bool
    {
        return (bool) config('chapa.enabled') && filled(config('chapa.secret_key'));
    }

    /** True for keys that start with CHAPA_TEST_. */
    public function isTestMode(): bool
    {
        return str_starts_with(strtoupper((string) config('chapa.secret_key')), 'CHAPA_TEST');
    }

    /**
     * Start a hosted payment. On success the answer holds data.checkout_url.
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $idempotencyKey  One key per new payment; reuse it only to retry the same payment.
     * @return array<string, mixed>|null
     */
    public function initialize(array $payload, string $idempotencyKey): ?array
    {
        return $this->call('post', '/payments/hosted', $payload, ['Idempotency-Key' => $idempotencyKey]);
    }

    /**
     * Ask Chapa what really happened to a payment. The reference can be Chapa's own reference or our
     * merchant_reference.
     *
     * @return array<string, mixed>|null
     */
    public function verify(string $reference): ?array
    {
        return $this->call('get', '/payments/'.rawurlencode($reference).'/verify');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array<string, mixed>|null
     */
    private function call(string $method, string $path, array $payload = [], array $headers = []): ?array
    {
        try {
            $http = Http::baseUrl(rtrim((string) config('chapa.base_url'), '/'))
                ->withToken((string) config('chapa.secret_key'))
                ->withHeaders($headers)
                ->acceptJson()
                ->timeout((int) config('chapa.timeout', 20));

            $response = $method === 'get' ? $http->get($path) : $http->asJson()->post($path, $payload);
        } catch (ConnectionException $e) {
            Log::warning('Chapa: could not reach the API.', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }

        if ($response->serverError()) {
            Log::warning('Chapa: the API had an error.', ['path' => $path, 'http' => $response->status()]);

            return null;
        }

        // 401 and 403 mean the key is wrong or belongs to the other environment (test key in live mode, or
        // the public key instead of the private key). That is a setup problem, so make it easy to find.
        if (in_array($response->status(), [401, 403], true)) {
            Log::error('Chapa: the API key was rejected. Check CHAPA_SECRET_KEY (private key, right environment).', [
                'path' => $path,
                'http' => $response->status(),
                'message' => $response->json('message'),
            ]);
        }

        // Other 4xx answers are still answers ("payment not found", "reference used before"), so they are returned.
        $body = $response->json();

        return is_array($body) ? $body : [];
    }
}
