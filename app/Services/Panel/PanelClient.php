<?php

namespace App\Services\Panel;

use App\Services\Panel\DTO\ClientCreated;
use App\Services\Panel\DTO\ServerInfo;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PanelClient
{
    private const TOKEN_CACHE_KEY = 'panel.api_token';

    // Панель выдаёт JWT на 30 дней — кешируем с запасом и перелогиниваемся по 401.
    private const TOKEN_CACHE_DAYS = 7;

    public function createClient(int $serverId, string $name): ClientCreated
    {
        $response = $this->request('post', '/api/clients/create', [
            'server_id' => $serverId,
            'name' => $name,
        ]);
        $this->ensureOk($response, 'clients/create');

        $created = ClientCreated::fromArray($response->json() ?? []);
        if ($created->id <= 0 || $created->config === '') {
            Log::error('panel clients/create returned unexpected payload', [
                'body' => mb_substr($response->body(), 0, 500),
            ]);
            throw new RuntimeException('Panel clients/create returned unexpected payload');
        }

        return $created;
    }

    public function revokeClient(int $clientId): void
    {
        $response = $this->request('post', "/api/clients/{$clientId}/revoke");
        $this->ensureOk($response, "clients/{$clientId}/revoke");
    }

    public function restoreClient(int $clientId): void
    {
        $response = $this->request('post', "/api/clients/{$clientId}/restore");
        $this->ensureOk($response, "clients/{$clientId}/restore");
    }

    /** @return ServerInfo[] */
    public function getServers(): array
    {
        $response = $this->request('get', '/api/servers');
        $this->ensureOk($response, 'servers');

        $data = $response->json();
        $items = is_array($data) ? ($data['servers'] ?? $data) : [];

        return array_map(fn (array $item) => ServerInfo::fromArray($item), $items);
    }

    /**
     * Запрос с авторизацией. При 401 (протух кешированный токен) — один
     * перелогин и повтор, чтобы интеграция не умирала раз в 30 дней.
     */
    private function request(string $method, string $uri, array $data = []): Response
    {
        $response = $this->http()->{$method}($uri, $data);

        if ($response->status() === 401 && $this->usesAutoLogin()) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $this->http()->{$method}($uri, $data);
        }

        return $response;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('panel.base_url'), '/'))
            ->withToken($this->token())
            ->acceptJson()
            ->timeout((int) config('panel.http_timeout_seconds', 30));
    }

    private function token(): string
    {
        $static = (string) config('panel.jwt_token');
        if ($static !== '') {
            return $static;
        }

        return (string) Cache::remember(
            self::TOKEN_CACHE_KEY,
            now()->addDays(self::TOKEN_CACHE_DAYS),
            fn () => $this->login()
        );
    }

    private function usesAutoLogin(): bool
    {
        return (string) config('panel.jwt_token') === '';
    }

    private function login(): string
    {
        $email = (string) config('panel.email');
        $password = (string) config('panel.password');
        if ($email === '' || $password === '') {
            throw new RuntimeException('Panel auth is not configured: set PANEL_JWT_TOKEN or PANEL_EMAIL + PANEL_PASSWORD');
        }

        // Эндпоинт панели читает $_POST — только form-encoded, не JSON.
        $response = Http::baseUrl(rtrim((string) config('panel.base_url'), '/'))
            ->asForm()
            ->acceptJson()
            ->timeout((int) config('panel.http_timeout_seconds', 30))
            ->post('/api/auth/token', [
                'email' => $email,
                'password' => $password,
            ]);

        $token = (string) ($response->json('token') ?? '');
        if (!$response->successful() || $token === '') {
            Log::error('panel login failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);
            throw new RuntimeException("Panel login failed: HTTP {$response->status()}");
        }

        return $token;
    }

    private function ensureOk(Response $response, string $endpoint): void
    {
        if (!$response->successful()) {
            Log::warning('panel request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);
            throw new RuntimeException("Panel {$endpoint} failed: HTTP {$response->status()}");
        }
    }
}
