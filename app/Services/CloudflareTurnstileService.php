<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\TurnstileWidget;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

class CloudflareTurnstileService
{
    private const BASE_URL = 'https://api.cloudflare.com/client/v4';

    private const SHARED_HOSTNAME = null;

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
     * Resolve a usable Turnstile widget for an export hostname.
     *
     * @param array{widget_scope?: string, mode?: string, allow_per_hostname_fallback?: bool} $options
     * @return array{success: bool, message: string, hostname?: string, widget?: TurnstileWidget, site_key?: string, secret_key?: string, scope?: string}
     */
    public function resolveTurnstileWidget(Organization $organization, string $hostname, array $options = []): array
    {
        $normalizedHostname = $this->normalizeHostname($hostname);
        if ($normalizedHostname === '') {
            return $this->provisioningFailure('A valid hostname is required before provisioning Turnstile.');
        }

        $organization->loadMissing('turnstileSetting');
        $setting = $organization->turnstileSetting;
        if (!$setting || !$setting->enabled || !$setting->auto_provision_enabled) {
            return $this->provisioningFailure('Turnstile auto-provisioning is not enabled for this organization.');
        }

        $accountId = trim((string) $setting->cloudflare_account_id);
        $apiToken = trim((string) $setting->cloudflare_api_token_encrypted);
        if ($accountId === '' || $apiToken === '') {
            return $this->provisioningFailure('Cloudflare Account ID and API token are required before provisioning Turnstile.');
        }

        $scope = $this->normalizeWidgetScope((string) ($options['widget_scope'] ?? $setting->widget_scope ?? 'shared'));
        $mode = $this->normalizeMode((string) ($options['mode'] ?? $setting->default_widget_mode ?? 'managed'));

        if ($scope === 'per_hostname') {
            return $this->resolvePerHostnameWidget($organization, $normalizedHostname, $accountId, $apiToken, $mode);
        }

        $shared = $this->resolveSharedWidget($organization, $normalizedHostname, $accountId, $apiToken, $mode);
        if ($shared['success'] || !($options['allow_per_hostname_fallback'] ?? true)) {
            return $shared;
        }

        if (!$this->looksLikeHostnameLimitFailure($shared['message'])) {
            return $shared;
        }

        return $this->resolvePerHostnameWidget($organization, $normalizedHostname, $accountId, $apiToken, $mode);
    }

    /**
     * @param list<string>|null $existingDomains
     * @return list<string>
     */
    public function mergeDomains(?array $existingDomains, string $hostname): array
    {
        return collect($existingDomains ?? [])
            ->push($hostname)
            ->map(fn ($domain) => $this->normalizeHostname($domain))
            ->filter()
            ->unique()
            ->values()
            ->all();
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

    /**
     * @return array{success: bool, message: string, hostname?: string, widget?: TurnstileWidget, site_key?: string, secret_key?: string, scope?: string}
     */
    private function resolveSharedWidget(Organization $organization, string $hostname, string $accountId, string $apiToken, string $mode): array
    {
        $widget = TurnstileWidget::query()
            ->where('organization_id', $organization->id)
            ->where('widget_scope', 'shared')
            ->orderBy('id')
            ->first();

        if (!$widget) {
            $name = $this->buildWidgetName($organization, 'shared');
            $created = $this->createWidget($accountId, $apiToken, $name, [$hostname], $mode);

            if (!$created['success']) {
                return $this->provisioningFailure($created['message']);
            }

            return $this->storeProvisionedWidget($organization, self::SHARED_HOSTNAME, 'shared', [$hostname], $mode, $created);
        }

        $domains = $this->mergeDomains($widget->domains_json, $hostname);
        if (in_array($hostname, $widget->domains_json ?? [], true)) {
            return $this->provisioningSuccess($widget, $hostname, 'shared');
        }

        $updated = $this->updateWidgetDomains(
            $accountId,
            $apiToken,
            $widget->site_key,
            $this->buildWidgetName($organization, 'shared'),
            $domains,
            $mode
        );

        if (!$updated['success']) {
            return $this->provisioningFailure($updated['message']);
        }

        $widget->fill([
            'mode' => $mode,
            'domains_json' => $domains,
            'last_synced_at' => now(),
        ])->save();

        return $this->provisioningSuccess($widget->refresh(), $hostname, 'shared');
    }

    /**
     * @return array{success: bool, message: string, hostname?: string, widget?: TurnstileWidget, site_key?: string, secret_key?: string, scope?: string}
     */
    private function resolvePerHostnameWidget(Organization $organization, string $hostname, string $accountId, string $apiToken, string $mode): array
    {
        $widget = TurnstileWidget::query()
            ->where('organization_id', $organization->id)
            ->where('widget_scope', 'per_hostname')
            ->where('hostname', $hostname)
            ->first();

        if ($widget) {
            return $this->provisioningSuccess($widget, $hostname, 'per_hostname');
        }

        $created = $this->createWidget(
            $accountId,
            $apiToken,
            $this->buildWidgetName($organization, 'per_hostname', $hostname),
            [$hostname],
            $mode
        );

        if (!$created['success']) {
            return $this->provisioningFailure($created['message']);
        }

        return $this->storeProvisionedWidget($organization, $hostname, 'per_hostname', [$hostname], $mode, $created);
    }

    /**
     * @param list<string> $domains
     * @param array{success: bool, message: string, status: int|null, result?: mixed} $cloudflareResult
     * @return array{success: bool, message: string, hostname?: string, widget?: TurnstileWidget, site_key?: string, secret_key?: string, scope?: string}
     */
    private function storeProvisionedWidget(Organization $organization, ?string $hostname, string $scope, array $domains, string $mode, array $cloudflareResult): array
    {
        $credentials = $this->extractWidgetCredentials($cloudflareResult['result'] ?? []);
        if ($credentials['site_key'] === '' || $credentials['secret_key'] === '') {
            return $this->provisioningFailure('Cloudflare did not return the required Turnstile site key and secret key.');
        }

        $widget = TurnstileWidget::query()->create([
            'organization_id' => $organization->id,
            'hostname' => $hostname,
            'cloudflare_widget_id' => $credentials['cloudflare_widget_id'] ?: $credentials['site_key'],
            'site_key' => $credentials['site_key'],
            'secret_key_encrypted' => $credentials['secret_key'],
            'mode' => $mode,
            'widget_scope' => $scope,
            'domains_json' => $domains,
            'last_synced_at' => now(),
        ]);

        return $this->provisioningSuccess($widget, $hostname ?? $domains[0] ?? '', $scope);
    }

    /**
     * @param mixed $result
     * @return array{site_key: string, secret_key: string, cloudflare_widget_id: string}
     */
    public function extractWidgetCredentials(mixed $result): array
    {
        if (!is_array($result)) {
            return ['site_key' => '', 'secret_key' => '', 'cloudflare_widget_id' => ''];
        }

        $siteKey = (string) ($result['sitekey'] ?? $result['site_key'] ?? $result['siteKey'] ?? '');
        $secretKey = (string) ($result['secret'] ?? $result['secret_key'] ?? $result['secretKey'] ?? '');
        $widgetId = (string) ($result['id'] ?? $result['widget_id'] ?? $siteKey);

        return [
            'site_key' => $siteKey,
            'secret_key' => $secretKey,
            'cloudflare_widget_id' => $widgetId,
        ];
    }

    private function buildWidgetName(Organization $organization, string $scope, ?string $hostname = null): string
    {
        $baseName = trim((string) $organization->name) ?: 'Builder';

        if ($scope === 'per_hostname' && $hostname) {
            return $baseName . ' - ' . $hostname;
        }

        return $baseName . ' - Landing Pages';
    }

    private function normalizeWidgetScope(string $scope): string
    {
        return $scope === 'per_hostname' ? 'per_hostname' : 'shared';
    }

    private function looksLikeHostnameLimitFailure(string $message): bool
    {
        return (bool) preg_match('/limit|maximum|max|too many|exceed/i', $message);
    }

    /**
     * @return array{success: true, message: string, hostname: string, widget: TurnstileWidget, site_key: string, secret_key: string, scope: string}
     */
    private function provisioningSuccess(TurnstileWidget $widget, string $hostname, string $scope): array
    {
        return [
            'success' => true,
            'message' => 'Turnstile widget resolved.',
            'hostname' => $hostname,
            'widget' => $widget,
            'site_key' => $widget->site_key,
            'secret_key' => $widget->secret_key_encrypted,
            'scope' => $scope,
        ];
    }

    /**
     * @return array{success: false, message: string}
     */
    private function provisioningFailure(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
        ];
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
