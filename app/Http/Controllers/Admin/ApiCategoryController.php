<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiCategory;
use App\Models\ApiCategoryField;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiCategoryController extends Controller
{
    public function index()
    {
        $categories = ApiCategory::withCount('fields')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:api_categories,name',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0'
        ]);

        $category = ApiCategory::create([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        $this->syncCategoryToCrm($category);

        return response()->json([
            'success' => true,
            'message' => 'API category created successfully.',
            'data' => $category
        ], 201);
    }

    public function show($id)
    {
        $category = ApiCategory::with('fields')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    public function update(Request $request, $id)
    {
        $category = ApiCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|unique:api_categories,name,' . $id,
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0'
        ]);

        $category->update($validated);

        $this->syncCategoryToCrm($category);

        return response()->json([
            'success' => true,
            'message' => 'API category updated successfully.',
            'data' => $category
        ]);
    }

    public function destroy($id)
    {
        $category = ApiCategory::findOrFail($id);

        // Check if category has user instances
        if ($category->userInstances()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with existing user instances.'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'API category deleted successfully.'
        ]);
    }

    public function toggleActive($id)
    {
        $category = ApiCategory::findOrFail($id);
        $category->is_active = !$category->is_active;
        $category->save();

        $this->syncCategoryToCrm($category);

        return response()->json([
            'success' => true,
            'message' => 'API category status updated successfully.',
            'data' => $category
        ]);
    }

    public function syncAllToCrm()
    {
        $categories = ApiCategory::with('fields')->get();
        $summary = [
            'categories' => 0,
            'fields' => 0,
            'errors' => []
        ];

        foreach ($categories as $category) {
            try {
                $this->syncCategoryToCrm($category);
                $summary['categories']++;
            } catch (\Exception $e) {
                $summary['errors'][] = "category: {$category->id} - {$e->getMessage()}";
            }

            foreach ($category->fields as $field) {
                try {
                    $this->syncFieldToCrm($field);
                    $summary['fields']++;
                } catch (\Exception $e) {
                    $summary['errors'][] = "field: {$field->id} - {$e->getMessage()}";
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'CRM sync completed.',
            'data' => $summary
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

    private function syncCategoryToCrm(ApiCategory $category): void
    {
        try {
            $host = request()->getHost();
            if ($host === 'localhost' || $host === '127.0.0.1') {
                return;
            }

            $payload = [
                'externalId' => (string) $category->id,
                'name' => $category->name,
                'is_active' => (bool) $category->is_active,
                'sort_order' => (int) $category->sort_order,
            ];

            $baseUrl = Setting::getCrmBaseUrl();
            $response = Http::withOptions(['verify' => Setting::getCrmVerifySsl()])
                ->timeout(15)
                ->post($baseUrl . '/api/v1/create-update-api-category', $payload);

            if (!$response->successful()) {
                Log::error('CRM API category sync failed', [
                    'category_id' => $category->id,
                    'payload' => $payload,
                    'response' => $response->json(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('CRM API category sync exception', [
                'category_id' => $category->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
