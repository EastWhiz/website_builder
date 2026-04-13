<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ValidateOrganizationMemberTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && Gate::forUser($user)->allows('org.permission', 'content.move_cross_org');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'source_organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'target_organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Please select a user to move.',
            'target_organization_id.required' => 'Please select the target organization.',
        ];
    }
}
