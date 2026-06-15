<?php

namespace App\Http\Controllers;

use App\Models\OtpService;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            $this->syncServiceToCrm($service);

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
            $this->syncServiceToCrm($service->fresh());

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

    private function syncServiceToCrm(OtpService $service): void
    {
        if (app()->environment('testing')) {
            return;
        }

        try {
            $baseUrl = Setting::getCrmBaseUrl();
            $externalCategoryId = 'otp-service-' . (string) $service->id;
            $categoryPayload = [
                'externalId' => $externalCategoryId,
                'name' => $service->name,
                'integration_group' => 'services',
                'is_active' => (bool) $service->is_active,
                'sort_order' => 0,
            ];

            $response = Http::withOptions(['verify' => Setting::getCrmVerifySsl()])
                ->timeout(15)
                ->post($baseUrl . '/api/v1/create-update-api-category', $categoryPayload);

            if (!$response->successful()) {
                Log::error('CRM OTP service category sync failed', [
                    'service_id' => $service->id,
                    'status' => $response->status(),
                    'response_body' => $response->body(),
                ]);
                return;
            }

            foreach (($service->fields ?? []) as $index => $field) {
                $fieldPayload = [
                    'externalCategoryId' => $externalCategoryId,
                    'externalId' => $externalCategoryId . '-field-' . (string) $index,
                    'name' => (string) ($field['name'] ?? 'field_' . $index),
                    'label' => (string) ($field['label'] ?? $field['name'] ?? 'Field'),
                    'type' => 'text',
                    'placeholder' => (string) ($field['placeholder'] ?? ''),
                    'is_required' => (bool) ($field['required'] ?? false),
                    'encrypt' => (bool) ($field['encrypt'] ?? false),
                ];

                $fieldResponse = Http::withOptions(['verify' => Setting::getCrmVerifySsl()])
                    ->timeout(15)
                    ->post($baseUrl . '/api/v1/create-update-api-category-field', $fieldPayload);

                if (!$fieldResponse->successful()) {
                    Log::error('CRM OTP service field sync failed', [
                        'service_id' => $service->id,
                        'field_index' => $index,
                        'status' => $fieldResponse->status(),
                        'response_body' => $fieldResponse->body(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('CRM OTP service category sync exception', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
