<?php

namespace App\Http\Controllers;

use App\Models\OtpService;
use Illuminate\Http\Request;

class OtpServiceController extends Controller
{
    /**
     * Get all active OTP services (for user selection)
     */
    public function index()
    {
        try {
            $services = OtpService::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'fields']);

            return response()->json([
                'success' => true,
                'message' => 'OTP services retrieved successfully.',
                'data' => $services
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving OTP services.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get service by ID (for form generation)
     */
    public function show($id)
    {
        try {
            $service = OtpService::where('is_active', true)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'OTP service retrieved successfully.',
                'data' => $service
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'OTP service not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get all OTP services (admin only - includes inactive)
     */
    public function adminIndex()
    {
        try {
            $services = OtpService::orderBy('name')->get();

            return response()->json([
                'success' => true,
                'message' => 'OTP services retrieved successfully.',
                'data' => $services
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving OTP services.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created OTP service (admin only)
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:otp_services,name',
                'fields' => 'required|array|min:1',
                'fields.*.name' => 'required|string|max:100',
                'fields.*.label' => 'required|string|max:255',
                'fields.*.required' => 'boolean',
                'fields.*.placeholder' => 'nullable|string|max:500',
                'fields.*.encrypt' => 'boolean',
                'is_active' => 'boolean',
            ]);

            $service = OtpService::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'OTP service created successfully.',
                'data' => $service
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating OTP service.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an OTP service (admin only)
     */
    public function update(Request $request, $id)
    {
        try {
            $service = OtpService::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:otp_services,name,' . $id,
                'fields' => 'required|array|min:1',
                'fields.*.name' => 'required|string|max:100',
                'fields.*.label' => 'required|string|max:255',
                'fields.*.required' => 'boolean',
                'fields.*.placeholder' => 'nullable|string|max:500',
                'fields.*.encrypt' => 'boolean',
                'is_active' => 'boolean',
            ]);

            $service->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'OTP service updated successfully.',
                'data' => $service->fresh()
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating OTP service.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an OTP service (admin only)
     */
    public function destroy($id)
    {
        try {
            $service = OtpService::findOrFail($id);

            // Check if any users have credentials for this service
            $hasCredentials = $service->userCredentials()->exists();

            if ($hasCredentials) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete service. Users have configured credentials for this service.',
                ], 422);
            }

            $service->delete();

            return response()->json([
                'success' => true,
                'message' => 'OTP service deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting OTP service.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
