<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

class CloudflareTurnstileService
{
    private const BASE_URL = 'https://api.cloudflare.com/client/v4';

    /**
     * Normalize any URL/domain input to the hostname Cloudflare expects.
     */
    public function normalizeHostname(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }

        $host = parse_url($value, PHP_URL_HOST);
        if (!is_string($host) || trim($host) === '') {
            return '';
        }

        return strtolower(rtrim(trim($host), '.'));
    }

    /**
     * @param list<string> $domains
     * @return array{name: string, domains: list<string>, mode: string}
     */
    public function buildWidgetPayload(string $name, array $domains, string $mode = 'managed'): array
    {
        $normalizedDomains = collect($domains)
            ->map(fn ($domain) => $this->normalizeHostname($domain))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($normalizedDomains === []) {
            throw new InvalidArgumentException('At least one valid hostname is required.');
        }

        return [
            'name' => trim($name) !== '' ? trim($name) : 'Builder Turnstile Widget',
            'domains' => $normalizedDomains,
            'mode' => $this->normalizeMode($mode),
        ];
    }

    /**
     * Read-only connection check against Cloudflare widget list endpoint.
     *
     * @return array{success: bool, message: string, status: int|null, result?: mixed}
     */
    public function testConnection(string $accountId, string $apiToken): array
    {
        return $this->request('GET', $this->widgetsPath($accountId), $apiToken);
    }

    /**
     * @param list<string> $domains
     * @return array{success: bool, message: string, status: int|null, result?: mixed}
     */
    public function createWidget(string $accountId, string $apiToken, string $name, array $domains, string $mode = 'managed'): array
    {
        return $this->request(
            'POST',
            $this->widgetsPath($accountId),
            $apiToken,
            $this->buildWidgetPayload($name, $domains, $mode)
        );
    }

    /**
     * @param list<string> $domains
     * @return array{success: bool, message: string, status: int|null, result?: mixed}
     */
    public function updateWidgetDomains(string $accountId, string $apiToken, string $siteKey, string $name, array $domains, string $mode = 'managed'): array
    {
        return $this->request(
            'PUT',
            $this->widgetPath($accountId, $siteKey),
            $apiToken,
            $this->buildWidgetPayload($name, $domains, $mode)
        );
    }

    /**
     * @return array{success: bool, message: string, status: int|null, result?: mixed}
     */
    public function listWidgets(string $accountId, string $apiToken): array
    {
        return $this->request('GET', $this->widgetsPath($accountId), $apiToken);
    }

    private function normalizeMode(string $mode): string
    {
        return in_array($mode, ['managed', 'non-interactive', 'invisible'], true)
            ? $mode
            : 'managed';
    }

    private function widgetsPath(string $accountId): string
    {
        $accountId = trim($accountId);
        if ($accountId === '') {
            throw new InvalidArgumentException('Cloudflare Account ID is required.');
        }

        return sprintf('/accounts/%s/challenges/widgets', rawurlencode($accountId));
    }

    private function widgetPath(string $accountId, string $siteKey): string
    {
        $siteKey = trim($siteKey);
        if ($siteKey === '') {
            throw new InvalidArgumentException('Cloudflare Turnstile site key is required.');
        }

        return $this->widgetsPath($accountId) . '/' . rawurlencode($siteKey);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{success: bool, message: string, status: int|null, result?: mixed}
     */
    private function request(string $method, string $path, string $apiToken, ?array $payload = null): array
    {
        $apiToken = trim($apiToken);
        if ($apiToken === '') {
            throw new InvalidArgumentException('Cloudflare API token is required.');
        }

        try {
            $pending = Http::withToken($apiToken)
                ->acceptJson()
                ->asJson()
                ->timeout(15);

            $response = match (strtoupper($method)) {
                'GET' => $pending->get(self::BASE_URL . $path),
                'POST' => $pending->post(self::BASE_URL . $path, $payload ?? []),
                'PUT' => $pending->put(self::BASE_URL . $path, $payload ?? []),
                default => throw new InvalidArgumentException('Unsupported Cloudflare request method.'),
            };
        } catch (ConnectionException $e) {
            return $this->failure('Cloudflare connection failed: ' . $e->getMessage(), null);
        } catch (Throwable $e) {
            return $this->failure('Cloudflare request failed: ' . $e->getMessage(), null);
        }

        return $this->normalizeResponse($response);
    }

    /**
     * @return array{success: bool, message: string, status: int|null, result?: mixed}
     */
    private function normalizeResponse(Response $response): array
    {
        $success = $response->successful() && $response->json('success') === true;
        $message = $success
            ? 'Cloudflare Turnstile request completed successfully.'
            : ($response->json('errors.0.message') ?: 'Cloudflare Turnstile request failed.');

        $result = [
            'success' => $success,
            'message' => $message,
            'status' => $response->status(),
        ];

        if ($response->json('result') !== null) {
            $result['result'] = $response->json('result');
        }

        return $result;
    }

    /**
     * @return array{success: false, message: string, status: int|null}
     */
    private function failure(string $message, ?int $status): array
    {
        return [
            'success' => false,
            'message' => $message,
            'status' => $status,
        ];
    }
}
