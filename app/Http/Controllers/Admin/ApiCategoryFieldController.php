<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiCategory;
use App\Models\ApiCategoryField;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiCategoryFieldController extends Controller
{
    private function denyUnlessCatalogPermission(Request $request, string $permissionKey)
    {
        $user = $request->user();
        if (!$user || !Gate::forUser($user)->allows('org.permission', $permissionKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.',
            ], 403);
        }

        return null;
    }

    public function store(Request $request, $categoryId)
    {
        if ($denied = $this->denyUnlessCatalogPermission($request, 'integration.catalog.update')) {
            return $denied;
        }

        $category = ApiCategory::findOrFail($categoryId);

        $validated = $request->validate([
            'name' => 'required|string',
            'label' => 'required|string',
            'type' => 'required|string|in:text,password,email,url,number,textarea',
            'placeholder' => 'nullable|string',
            'is_required' => 'boolean',
            'encrypt' => 'boolean',
        ]);

        $field = ApiCategoryField::create([
            'api_category_id' => $categoryId,
            'name' => $validated['name'],
            'label' => $validated['label'],
            'type' => $validated['type'],
            'placeholder' => $validated['placeholder'] ?? null,
            'is_required' => $validated['is_required'] ?? false,
            'encrypt' => $validated['encrypt'] ?? false,
        ]);

        $this->syncFieldToCrm($field);

        return response()->json([
            'success' => true,
            'message' => 'Field created successfully.',
            'data' => $field
        ], 201);
    }

    public function update(Request $request, $categoryId, $fieldId)
    {
        if ($denied = $this->denyUnlessCatalogPermission($request, 'integration.catalog.update')) {
            return $denied;
        }

        $field = ApiCategoryField::where('api_category_id', $categoryId)
            ->findOrFail($fieldId);

        $validated = $request->validate([
            'name' => 'required|string',
            'label' => 'required|string',
            'type' => 'required|string|in:text,password,email,url,number,textarea',
            'placeholder' => 'nullable|string',
            'is_required' => 'boolean',
            'encrypt' => 'boolean',
        ]);

        $field->update($validated);

        $this->syncFieldToCrm($field);

        return response()->json([
            'success' => true,
            'message' => 'Field updated successfully.',
            'data' => $field
        ]);
    }

    public function destroy($categoryId, $fieldId)
    {
        $request = request();
        if ($denied = $this->denyUnlessCatalogPermission($request, 'integration.catalog.archive')) {
            return $denied;
        }

        $field = ApiCategoryField::where('api_category_id', $categoryId)
            ->findOrFail($fieldId);

        $field->delete();

        return response()->json([
            'success' => true,
            'message' => 'Field deleted successfully.'
        ]);
    }

    private function syncFieldToCrm(ApiCategoryField $field): void
    {
        try {
            $host = request()->getHost();
            if ($host === 'localhost' || $host === '127.0.0.1') {
                return;
            }

            $payload = [
                'externalCategoryId' => (string) $field->api_category_id,
                'externalId' => (string) $field->id,
                'name' => $field->name,
                'label' => $field->label,
                'type' => $field->type,
                'placeholder' => $field->placeholder,
                'is_required' => (bool) $field->is_required,
                'encrypt' => (bool) $field->encrypt,
            ];

            $baseUrl = Setting::getCrmBaseUrl();
            $response = Http::withOptions(['verify' => Setting::getCrmVerifySsl()])
                ->timeout(15)
                ->post($baseUrl . '/api/v1/create-update-api-category-field', $payload);

            if (!$response->successful()) {
                Log::error('CRM API category field sync failed', [
                    'field_id' => $field->id,
                    'payload' => $payload,
                    'response' => $response->json(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('CRM API category field sync exception', [
                'field_id' => $field->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
