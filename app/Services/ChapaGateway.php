<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to Chapa's REST API (https://developer.chapa.co). The chapa/chapa-laravel package is not used
 * because it does not support Laravel 13.
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

    /** True for keys that start with CHASECK_TEST-. */
    public function isTestMode(): bool
    {
        return str_contains(strtoupper((string) config('chapa.secret_key')), 'TEST');
    }

    /**
     * Start a payment. On success the answer holds data.checkout_url.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function initialize(array $payload): ?array
    {
        return $this->call('post', '/transaction/initialize', $payload);
    }

    /**
     * Ask Chapa what really happened to a payment.
     *
     * @return array<string, mixed>|null
     */
    public function verify(string $txRef): ?array
    {
        return $this->call('get', '/transaction/verify/'.rawurlencode($txRef));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function call(string $method, string $path, array $payload = []): ?array
    {
        try {
            $http = Http::baseUrl(rtrim((string) config('chapa.base_url'), '/'))
                ->withToken((string) config('chapa.secret_key'))
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

        // A 4xx answer is still an answer ("not found", "not paid yet", "invalid field"), so it is returned.
        $body = $response->json();

        return is_array($body) ? $body : [];
    }
}
