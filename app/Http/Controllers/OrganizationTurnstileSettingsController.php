<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrganizationTurnstileSettingsRequest;
use App\Models\OrganizationTurnstileSetting;
use App\Services\CloudflareTurnstileService;
use App\Support\OrganizationAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationTurnstileSettingsController extends Controller
{
    public function __construct(private readonly CloudflareTurnstileService $turnstileService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $organization = $this->authorizedOrganization($request);
        $setting = $organization->turnstileSetting;

        return response()->json([
            'enabled' => (bool) ($setting?->enabled ?? false),
            'auto_provision_enabled' => (bool) ($setting?->auto_provision_enabled ?? false),
            'cloudflare_account_id' => $setting?->cloudflare_account_id ?? '',
            'cloudflare_api_token_exists' => (bool) ($setting?->cloudflare_api_token_encrypted ?? false),
            'default_widget_mode' => $setting?->default_widget_mode ?? 'managed',
            'widget_scope' => $setting?->widget_scope ?? 'shared',
        ]);
    }

    public function update(UpdateOrganizationTurnstileSettingsRequest $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization();
        if (!$organization) {
            abort(422, 'No active organization context found.');
        }

        $validated = $request->validated();
        $existing = $organization->turnstileSetting;

        $values = [
            'enabled' => (bool) $validated['enabled'],
            'auto_provision_enabled' => (bool) $validated['auto_provision_enabled'],
            'cloudflare_account_id' => trim((string) ($validated['cloudflare_account_id'] ?? '')) ?: null,
            'default_widget_mode' => $validated['default_widget_mode'],
            'widget_scope' => $validated['widget_scope'],
        ];

        $newToken = trim((string) ($validated['cloudflare_api_token'] ?? ''));
        if ($newToken !== '') {
            $values['cloudflare_api_token_encrypted'] = $newToken;
        } elseif (!$existing) {
            $values['cloudflare_api_token_encrypted'] = null;
        }

        $setting = OrganizationTurnstileSetting::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            $values
        );

        return response()->json([
            'message' => 'Turnstile settings saved.',
            'settings' => [
                'enabled' => (bool) $setting->enabled,
                'auto_provision_enabled' => (bool) $setting->auto_provision_enabled,
                'cloudflare_account_id' => $setting->cloudflare_account_id ?? '',
                'cloudflare_api_token_exists' => (bool) $setting->cloudflare_api_token_encrypted,
                'default_widget_mode' => $setting->default_widget_mode,
                'widget_scope' => $setting->widget_scope,
            ],
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $organization = $this->authorizedOrganization($request);
        $setting = $organization->turnstileSetting;

        if (!$setting || !$setting->auto_provision_enabled) {
            return response()->json([
                'message' => 'Enable Turnstile auto-provisioning before testing the Cloudflare connection.',
            ], 422);
        }

        if (!$setting->cloudflare_account_id || !$setting->cloudflare_api_token_encrypted) {
            return response()->json([
                'message' => 'Cloudflare Account ID and API token are required before testing the connection.',
            ], 422);
        }

        $result = $this->turnstileService->testConnection(
            $setting->cloudflare_account_id,
            $setting->cloudflare_api_token_encrypted
        );

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'status' => $result['status'],
            ], 422);
        }

        return response()->json([
            'message' => 'Cloudflare Turnstile connection test passed.',
        ]);
    }

    private function authorizedOrganization(Request $request)
    {
        $user = $request->user();
        $organization = $user?->currentOrganization();
        if (!$user || !$organization) {
            abort(422, 'No active organization context found.');
        }

        if (!OrganizationAccess::canUserFullyManageTeam($user, $organization)) {
            abort(403, 'Unauthorized action.');
        }

        return $organization;
    }
}
