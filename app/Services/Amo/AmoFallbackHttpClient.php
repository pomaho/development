<?php

namespace App\Services\Amo;

use App\Models\AmoAccount;
use App\Models\ApiRequestLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AmoFallbackHttpClient
{
    public function __construct(private readonly AmoTokenManager $tokenManager)
    {
    }

    public function get(AmoAccount $account, string $path, array $query = []): array
    {
        return $this->request($account, 'GET', $path, $query);
    }

    public function post(AmoAccount $account, string $path, array $payload = []): array
    {
        return $this->request($account, 'POST', $path, $payload);
    }

    public function patch(AmoAccount $account, string $path, array $payload = []): array
    {
        return $this->request($account, 'PATCH', $path, $payload);
    }

    public function delete(AmoAccount $account, string $path, array $payload = []): array
    {
        return $this->request($account, 'DELETE', $path, $payload);
    }

    public function request(AmoAccount $account, string $method, string $path, array $data = []): array
    {
        $url = 'https://'.$account->base_domain.'/'.ltrim($path, '/');
        $started = microtime(true);
        $status = null;
        $responsePayload = null;
        $error = null;

        try {
            $token = $this->tokenManager->accessTokenFor($account);
            $request = Http::withToken($token)->acceptJson()->timeout(30);
            $response = $method === 'GET'
                ? $request->get($url, $data)
                : $request->{strtolower($method)}($url, $data);

            $status = $response->status();
            $responsePayload = $this->jsonPayload($response);

            $this->handleStatus($account, $status, $url, $responsePayload);

            return $responsePayload ?? [];
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            throw $exception;
        } finally {
            ApiRequestLog::query()->create([
                'amo_account_id' => $account->id,
                'method' => strtoupper($method),
                'url' => $this->safeUrl($url),
                'status_code' => $status,
                'request_payload' => $this->safePayload($data),
                'response_payload' => $this->safePayload($responsePayload),
                'error_message' => $error,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
        }
    }

    private function handleStatus(AmoAccount $account, int $status, string $url, ?array $payload): void
    {
        if ($status < 400) {
            $account->forceFill(['auth_status' => 'ok'])->save();
            return;
        }

        if ($status === 401) {
            $account->forceFill(['auth_status' => 'reauth_required'])->save();
        }

        $message = match ($status) {
            401 => 'amoCRM authorization failed or token expired.',
            402 => 'amoCRM subscription or access problem.',
            403 => 'amoCRM access forbidden.',
            404 => 'amoCRM endpoint or entity not found.',
            429 => 'amoCRM rate limit exceeded.',
            500, 502, 503, 504 => 'amoCRM temporary API error.',
            default => 'amoCRM API error.',
        };

        throw new RuntimeException($message.' '.$url.' Status: '.$status.' Response: '.json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    private function jsonPayload(Response $response): ?array
    {
        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    private function safeUrl(string $url): string
    {
        $parts = parse_url($url);

        if (! isset($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $query);
        $query = $this->safePayload($query);

        return strtok($url, '?').'?'.http_build_query($query);
    }

    private function safePayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        return $this->truncatePayload($this->redactPayload($payload));
    }

    private function redactPayload(array $payload): array
    {
        return Arr::mapWithKeys($payload, function ($value, $key): array {
            $lower = mb_strtolower((string) $key);
            if (in_array($lower, ['authorization', 'access_token', 'refresh_token', 'client_secret'], true)) {
                return [$key => '[redacted]'];
            }

            return [$key => is_array($value) ? $this->redactPayload($value) : $value];
        });
    }

    private function truncatePayload(array $payload): array
    {
        $maxBytes = max(0, (int) config('amo.api_log_payload_max_bytes', 16384));

        if ($maxBytes === 0) {
            return [
                '_truncated' => true,
                '_reason' => 'API payload logging is disabled.',
            ];
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false || strlen($json) <= $maxBytes) {
            return $payload;
        }

        return [
            '_truncated' => true,
            '_original_bytes' => strlen($json),
            '_stored_bytes_limit' => $maxBytes,
            '_top_level_keys' => array_slice(array_map('strval', array_keys($payload)), 0, 30),
            '_embedded_keys' => isset($payload['_embedded']) && is_array($payload['_embedded'])
                ? array_slice(array_map('strval', array_keys($payload['_embedded'])), 0, 30)
                : [],
            '_page' => $payload['_page'] ?? null,
            '_page_count' => $payload['_page_count'] ?? null,
        ];
    }
}
