<?php

namespace App\Services;

use App\Exceptions\MealieApiException;
use App\Exceptions\MealieConnectionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MealieClient
{
    /**
     * @param  string  $authHeader  The full "Bearer <token>" header forwarded from the MCP client.
     */
    public function __construct(
        private readonly string $authHeader,
    ) {}

    public function get(string $path, array $params = []): mixed
    {
        return $this->request('get', $path, ['query' => $params]);
    }

    public function post(string $path, array $data, array $query = []): mixed
    {
        return $this->request('post', $path, ['json' => $data, 'query' => $query]);
    }

    public function put(string $path, array $data, array $query = []): mixed
    {
        return $this->request('put', $path, ['json' => $data, 'query' => $query]);
    }

    public function delete(string $path, array $query = []): mixed
    {
        return $this->request('delete', $path, ['query' => $query]);
    }

    private function request(string $method, string $path, array $options = []): mixed
    {
        // Short id so the request and response log lines can be correlated.
        $requestId = (string) Str::uuid();

        $query = $options['query'] ?? [];
        $url = config('mealie.base_url').'/api/'.ltrim($path, '/')
            .($query ? '?'.http_build_query($query) : '');

        $http = Http::withHeaders(['Authorization' => $this->authHeader])->acceptJson();

        $payload = $options['json'] ?? [];

        $this->logRequest($requestId, $method, $url, $payload);

        $startedAt = microtime(true);

        try {
            $response = match ($method) {
                'get' => $http->get($url),
                'post' => $http->post($url, $payload),
                'put' => $http->put($url, $payload),
                'delete' => $http->delete($url),
            };
        } catch (ConnectionException $e) {
            // Could not reach Mealie at all (DNS, TLS, timeout, refused).
            Log::error('Mealie API request failed to connect', [
                'request_id' => $requestId,
                'method' => strtoupper($method),
                'url' => $url,
                'message' => $e->getMessage(),
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            throw new MealieConnectionException(config('mealie.base_url'), $e->getMessage(), $e);
        }

        $durationMs = $this->elapsedMs($startedAt);

        if ($response->failed()) {
            // Always log failures at error level so they land in the log
            // regardless of LOG_LEVEL. The body usually contains the exact
            // Mealie validation message (e.g. a 422 field error).
            Log::error('Mealie API request failed', [
                'request_id' => $requestId,
                'method' => strtoupper($method),
                'url' => $url,
                'status' => $response->status(),
                'reason' => $response->reason(),
                'request_payload' => $payload,
                'response_body' => $this->truncate($response->body()),
                'duration_ms' => $durationMs,
            ]);

            throw new MealieApiException(
                $response->status(),
                $response->body(),
                strtoupper($method),
                $path,
            );
        }

        $this->logResponse($requestId, $method, $url, $response->status(), $response->body(), $durationMs);

        return $response->status() === 204 ? null : $response->json();
    }

    private function logRequest(string $requestId, string $method, string $url, array $payload): void
    {
        if (! config('mealie.debug')) {
            return;
        }

        // Logged at debug level — requires LOG_LEVEL=debug in .env to appear.
        Log::debug('Mealie API request', [
            'request_id' => $requestId,
            'method' => strtoupper($method),
            'url' => $url,
            'payload' => $payload,
        ]);
    }

    private function logResponse(string $requestId, string $method, string $url, int $status, string $body, float $durationMs): void
    {
        if (! config('mealie.debug')) {
            return;
        }

        Log::debug('Mealie API response', [
            'request_id' => $requestId,
            'method' => strtoupper($method),
            'url' => $url,
            'status' => $status,
            'body' => $this->truncate($body),
            'duration_ms' => $durationMs,
        ]);
    }

    private function elapsedMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 1);
    }

    /**
     * Keep response bodies from flooding the log while preserving the useful head.
     */
    private function truncate(string $body, int $limit = 4000): string
    {
        return strlen($body) > $limit
            ? substr($body, 0, $limit).'… ['.(strlen($body) - $limit).' more chars]'
            : $body;
    }
}
