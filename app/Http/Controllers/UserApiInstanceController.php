<?php

namespace App\Http\Controllers;

use App\Models\ApiCategory;
use App\Models\UserApiInstance;
use App\Models\UserApiInstanceValue;
use App\Services\ApiInstanceValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserApiInstanceController extends Controller
{
    public function __construct(
        protected ApiInstanceValidationService $validationService
    ) {
    }
    /**
     * List user's API instances, grouped by category.
     */
    public function index(Request $request)
    {
        $instances = Auth::user()
            ->apiInstances()
            ->with(['category', 'values.field'])
            ->orderBy('api_category_id')
            ->orderBy('name')
            ->get();

        $grouped = $instances->groupBy('api_category_id')->map(function ($items) {
            $category = $items->first()->category;
            if (!$category) {
                return null;
            }
            return [
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                ],
                'instances' => $items->map(fn ($i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'is_active' => $i->is_active,
                    'credentials' => $i->credentials,
                ])->values()->all(),
            ];
        })->filter()->values()->all();

        return response()->json([
            'success' => true,
            'data' => $grouped,
        ]);
    }

    /**
     * Create new API instance.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'api_category_id' => 'required|integer|exists:api_categories,id',
            'name' => 'required|string|max:255',
            'values' => 'required|array',
        ]);

        $category = ApiCategory::with('fields')->findOrFail($validated['api_category_id']);
        $validator = $this->validationService->validate($request->all(), $category);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $values = $validated['values'];

        $instance = UserApiInstance::create([
            'user_id' => Auth::id(),
            'api_category_id' => $category->id,
            'name' => $validated['name'],
            'is_active' => true,
        ]);

        foreach ($category->fields as $field) {
            $value = $values[$field->name] ?? null;
            if ($value !== null && $value !== '') {
                $val = new UserApiInstanceValue();
                $val->user_api_instance_id = $instance->id;
                $val->api_category_field_id = $field->id;
                $val->setRelation('field', $field);
                $val->value = $value;
                $val->save();
            }
        }

        $instance->load(['category', 'values.field']);

        return response()->json([
            'success' => true,
            'message' => 'API instance created successfully.',
            'data' => [
                'id' => $instance->id,
                'name' => $instance->name,
                'is_active' => $instance->is_active,
                'credentials' => $instance->credentials,
            ],
        ], 201);
    }

    /**
     * Get API instance details (user must own it).
     */
    public function show($id)
    {
        $instance = UserApiInstance::with(['category', 'values.field'])
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $instance->id,
                'name' => $instance->name,
                'is_active' => $instance->is_active,
                'api_category_id' => $instance->api_category_id,
                'category' => ['id' => $instance->category->id, 'name' => $instance->category->name],
                'credentials' => $instance->credentials,
            ],
        ]);
    }

    /**
     * Update API instance (user must own it).
     */
    public function update(Request $request, $id)
    {
        $instance = UserApiInstance::with('category.fields')
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'values' => 'sometimes|array',
        ]);

        if (isset($validated['name'])) {
            $instance->update(['name' => $validated['name']]);
        }

        if (isset($validated['values'])) {
            $values = $validated['values'];
            $validator = $this->validationService->validate($request->all(), $instance->category);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            foreach ($instance->category->fields as $field) {
                $value = $values[$field->name] ?? null;
                $record = UserApiInstanceValue::firstOrNew([
                    'user_api_instance_id' => $instance->id,
                    'api_category_field_id' => $field->id,
                ]);
                $record->setRelation('field', $field);
                $record->value = $value ?? '';
                $record->save();
            }
        }

        $instance->load(['category', 'values.field']);

        return response()->json([
            'success' => true,
            'message' => 'API instance updated successfully.',
            'data' => [
                'id' => $instance->id,
                'name' => $instance->name,
                'is_active' => $instance->is_active,
                'credentials' => $instance->credentials,
            ],
        ]);
    }

    /**
     * Delete API instance (user must own it).
     */
    public function destroy($id)
    {
        $instance = UserApiInstance::where('user_id', Auth::id())->findOrFail($id);
        $instance->delete();

        return response()->json([
            'success' => true,
            'message' => 'API instance deleted successfully.',
        ]);
    }

    /**
     * Toggle active status (user must own it).
     */
    public function toggleActive($id)
    {
        $instance = UserApiInstance::where('user_id', Auth::id())->findOrFail($id);
        $instance->update(['is_active' => !$instance->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'API instance updated successfully.',
            'data' => ['is_active' => $instance->fresh()->is_active],
        ]);
    }

    /**
     * List active API categories with fields (for profile / instance creation).
     */
    public function categories()
    {
        $categories = ApiCategory::active()
            ->with('fields')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get user's instances for a category.
     */
    public function getByCategory($categoryId)
    {
        $instances = Auth::user()
            ->apiInstances()
            ->with(['category', 'values.field'])
            ->where('api_category_id', $categoryId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $instances->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'is_active' => $i->is_active,
                'credentials' => $i->credentials,
            ]),
        ]);
    }
}
