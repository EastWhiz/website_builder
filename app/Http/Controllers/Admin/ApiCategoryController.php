<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiCategory;
use Illuminate\Http\Request;

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

        return response()->json([
            'success' => true,
            'message' => 'API category status updated successfully.',
            'data' => $category
        ]);
    }
}
