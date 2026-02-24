<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiCategory;
use App\Models\ApiCategoryField;
use Illuminate\Http\Request;

class ApiCategoryFieldController extends Controller
{
    public function store(Request $request, $categoryId)
    {
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

        return response()->json([
            'success' => true,
            'message' => 'Field created successfully.',
            'data' => $field
        ], 201);
    }

    public function update(Request $request, $categoryId, $fieldId)
    {
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

        return response()->json([
            'success' => true,
            'message' => 'Field updated successfully.',
            'data' => $field
        ]);
    }

    public function destroy($categoryId, $fieldId)
    {
        $field = ApiCategoryField::where('api_category_id', $categoryId)
            ->findOrFail($fieldId);

        $field->delete();

        return response()->json([
            'success' => true,
            'message' => 'Field deleted successfully.'
        ]);
    }

}
