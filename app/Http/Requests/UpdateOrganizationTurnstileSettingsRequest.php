<?php

namespace App\Http\Requests;

use App\Support\OrganizationAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrganizationTurnstileSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $organization = $user->currentOrganization();
        if (!$organization) {
            return false;
        }

        return OrganizationAccess::canUserFullyManageTeam($user, $organization);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'auto_provision_enabled' => ['required', 'boolean'],
            'cloudflare_account_id' => ['nullable', 'string', 'max:255'],
            'cloudflare_api_token' => ['nullable', 'string', 'max:2000'],
            'default_widget_mode' => ['required', 'string', 'in:managed,non-interactive,invisible'],
            'widget_scope' => ['required', 'string', 'in:shared,per_hostname'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->boolean('auto_provision_enabled')) {
                return;
            }

            if (trim((string) $this->input('cloudflare_account_id', '')) === '') {
                $validator->errors()->add(
                    'cloudflare_account_id',
                    'The Cloudflare Account ID is required when auto-provisioning is enabled.'
                );
            }

            $organization = $this->user()?->currentOrganization();
            $hasSavedToken = (bool) $organization?->turnstileSetting?->cloudflare_api_token_encrypted;
            $hasNewToken = trim((string) $this->input('cloudflare_api_token', '')) !== '';

            if (!$hasSavedToken && !$hasNewToken) {
                $validator->errors()->add(
                    'cloudflare_api_token',
                    'The Cloudflare API token is required when auto-provisioning is enabled.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'default_widget_mode.in' => 'Choose a valid Turnstile widget mode.',
            'widget_scope.in' => 'Choose shared widget or per-hostname widget scope.',
        ];
    }
}
