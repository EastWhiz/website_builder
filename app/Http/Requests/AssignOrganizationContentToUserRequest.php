<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssignOrganizationContentToUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && Gate::forUser($user)->allows('org.permission', 'content.transfer_in_org');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.type' => ['required', 'string', Rule::in(['angle', 'angle_template', 'thank_you_page', 'user_api_instance'])],
            'items.*.id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_user_id.required' => 'Select the user to assign content to.',
            'items.required' => 'Select at least one item to assign.',
            'items.max' => 'You can assign at most 200 items in one request.',
        ];
    }
}
