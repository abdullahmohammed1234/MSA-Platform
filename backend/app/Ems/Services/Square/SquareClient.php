<?php

namespace App\Ems\Services\Square;

use App\Ems\Exceptions\EmsException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Low-level Square Connect v2 HTTP client.
 *
 * Uses Laravel's HTTP client (no Square SDK). Access tokens never leave
 * this class. Square-Version is pinned so webhook/API shapes stay stable.
 */
class SquareClient
{
    public const SQUARE_VERSION = '2026-07-15';

    public function enabled(): bool
    {
        return (bool) config('ems.payments.enabled', false)
            && $this->accessToken() !== ''
            && $this->locationId() !== '';
    }

    public function locationId(): string
    {
        return (string) config('ems.payments.square.location_id', '');
    }

    public function environment(): string
    {
        return strtolower((string) config('ems.payments.square.environment', 'sandbox'));
    }

    public function baseUrl(): string
    {
        return $this->environment() === 'production'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = [], ?string $idempotencyKey = null): array
    {
        return $this->request('post', $path, $payload, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('get', $path, $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $path): array
    {
        return $this->request('delete', $path);
    }

    public function ping(string $path): bool
    {
        try {
            $this->get($path);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = [], ?string $idempotencyKey = null): array
    {
        if (! $this->enabled()) {
            throw new EmsException(
                'Square is not configured.',
                [],
                HttpResponse::HTTP_SERVICE_UNAVAILABLE
            );
        }

        $pending = Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(20)
            ->baseUrl($this->baseUrl())
            ->withHeaders([
                'Square-Version' => (string) config('ems.payments.square.api_version', self::SQUARE_VERSION),
            ]);

        if ($idempotencyKey) {
            $pending = $pending->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        /** @var Response $response */
        $response = match ($method) {
            'get' => $pending->get($path, $payload),
            'delete' => $pending->delete($path),
            default => $pending->post($path, $payload),
        };

        if (! $response->successful()) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->error('ems.square.http_failed', [
                    'method' => $method,
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => $this->safeBody($response->json()),
                ]);

            throw new EmsException(
                $this->errorMessage($response) ?: 'Square request failed.',
                [],
                $response->status() >= 500
                    ? HttpResponse::HTTP_BAD_GATEWAY
                    : HttpResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function accessToken(): string
    {
        return (string) config('ems.payments.square.access_token', '');
    }

    /**
     * @param  mixed  $body
     * @return array<string, mixed>
     */
    private function safeBody(mixed $body): array
    {
        if (! is_array($body)) {
            return [];
        }

        unset($body['access_token'], $body['authorization'], $body['idempotency_key']);

        return $body;
    }

    private function errorMessage(Response $response): string
    {
        $errors = $response->json('errors');
        if (is_array($errors) && isset($errors[0]['detail'])) {
            return (string) $errors[0]['detail'];
        }

        return '';
    }
}
