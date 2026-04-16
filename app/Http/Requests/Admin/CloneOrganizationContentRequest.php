<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CloneOrganizationContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && Gate::forUser($user)->allows('org.permission', 'content.clone_cross_org');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_organization_id' => ['required', 'integer', 'exists:organizations,id', 'different:target_organization_id'],
            'target_organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
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
            'source_organization_id.required' => 'Select the source organization.',
            'target_organization_id.required' => 'Select the target organization.',
            'items.required' => 'Select at least one item to clone.',
            'items.max' => 'You can clone at most 100 items in one request.',
        ];
    }
}
