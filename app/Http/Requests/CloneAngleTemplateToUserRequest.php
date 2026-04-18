<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Support\OrganizationAccess;
use Illuminate\Foundation\Http\FormRequest;

class CloneAngleTemplateToUserRequest extends FormRequest
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

        if (OrganizationAccess::isPrivilegedPlatformAdmin($user)) {
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
            $current = $user->currentOrganization();
            if (!$current || (int) $current->id !== $orgId) {
                return null;
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
            'angle_template_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_user_id.required' => 'Select the user to receive the cloned landing page.',
            'angle_template_id.required' => 'Select a landing page to clone.',
        ];
    }
}
