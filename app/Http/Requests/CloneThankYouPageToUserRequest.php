<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Support\OrganizationAccess;
use Illuminate\Foundation\Http\FormRequest;

class CloneThankYouPageToUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $organization = $this->resolveTargetOrganization();
        if (!$organization) {
            return false;
        }

        return OrganizationAccess::canUserFullyManageTeam($user, $organization);
    }

    private function resolveTargetOrganization(): ?Organization
    {
        $user = $this->user();
        if (!$user) {
            return null;
        }

        $requestedOrgId = $this->input('organization_id');
        if ($requestedOrgId !== null && $requestedOrgId !== '') {
            $orgId = (int) $requestedOrgId;
            if (!OrganizationAccess::isPrivilegedPlatformAdmin($user)) {
                $current = $user->currentOrganization();
                if (!$current || (int) $current->id !== $orgId) {
                    return null;
                }
            }

            return Organization::find($orgId);
        }

        return $user->currentOrganization();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'thank_you_page_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_user_id.required' => 'Select the user to receive the cloned thank you page.',
            'thank_you_page_id.required' => 'Select a thank you page to clone.',
        ];
    }
}
