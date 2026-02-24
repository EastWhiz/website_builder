<?php

namespace App\Services;

use App\Models\ApiCategory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;

class ApiInstanceValidationService
{
    /**
     * Build Laravel validation rules from category field definitions.
     * Returns rules for the 'values' array, e.g. ['values.api_key' => 'required|string', ...]
     */
    public function buildRules(ApiCategory $category): array
    {
        $category->loadMissing('fields');
        $rules = [];
        $messages = [];

        foreach ($category->fields as $field) {
            $key = 'values.' . $field->name;
            $base = $field->is_required ? 'required|' : 'nullable|';
            $typeRule = $this->getRuleForType($field->type);
            $rules[$key] = $base . $typeRule;
            $messages[$key . '.required'] = "Field \"{$field->label}\" is required.";
        }

        return ['rules' => $rules, 'messages' => $messages];
    }

    /**
     * Validate data against category field definitions.
     * Returns the Validator instance (call ->fails() / ->errors() in controller).
     */
    public function validate(array $data, ApiCategory $category): ValidatorInstance
    {
        $built = $this->buildRules($category);
        return Validator::make($data, $built['rules'], $built['messages']);
    }

    private function getRuleForType(string $type): string
    {
        return match ($type) {
            'email' => 'string|email',
            'url' => 'string|url',
            'number' => 'numeric',
            'textarea' => 'string',
            'password', 'text' => 'string',
            default => 'string',
        };
    }
}
