<?php

namespace App\Http\Controllers;

use App\Models\OtpService;
use App\Models\OtpServiceCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class OtpServiceCredentialController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $user = Auth::user();
            $credentials = OtpServiceCredential::where('user_id', $user->id)
                ->with('service:id,name')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($credential) {
                    return [
                        'id' => $credential->id,
                        'service_id' => $credential->service_id,
                        'service_name' => $credential->service->name ?? null,
                        'credentials' => $credential->decrypted_credentials, // Auto-decrypted
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'OTP service credentials retrieved successfully.',
                'data' => $credentials
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving OTP service credentials.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'service_id' => 'required|exists:otp_services,id',
                'credentials' => 'required|array',
            ]);

            $user = Auth::user();
            $service = OtpService::findOrFail($validated['service_id']);

            // Build dynamic validation rules from service field definitions
            $validationRules = $this->buildValidationRules($service->fields);
            
            // Build nested validation rules for credentials.*
            $credentialsRules = [];
            foreach ($validationRules as $fieldName => $rules) {
                $credentialsRules['credentials.' . $fieldName] = $rules;
            }
            
            // Validate credentials against service field definitions
            $validatedCredentials = $request->validate($credentialsRules);

            // Update or create credentials
            $credential = OtpServiceCredential::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'service_id' => $validated['service_id']
                ],
                [
                    'credentials' => $request->input('credentials') // Will be encrypted by mutator
                ]
            );

            // Load service relationship for response
            $credential->load('service');

            return response()->json([
                'success' => true,
                'message' => 'OTP service credentials saved successfully.',
                'data' => [
                    'id' => $credential->id,
                    'service_id' => $credential->service_id,
                    'service_name' => $credential->service->name,
                    'credentials' => $credential->decrypted_credentials
                ]
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving OTP service credentials.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $user = Auth::user();
            $credential = OtpServiceCredential::where('id', $id)
                ->where('user_id', $user->id)
                ->with('service:id,name,fields')
                ->first();

            if (!$credential) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP service credential not found.',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP service credential retrieved successfully.',
                'data' => [
                    'id' => $credential->id,
                    'service_id' => $credential->service_id,
                    'service_name' => $credential->service->name,
                    'service_fields' => $credential->service->fields,
                    'credentials' => $credential->decrypted_credentials
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving OTP service credential.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get OTP service credentials by service ID
     */
    public function getByServiceId(string $serviceId)
    {
        try {
            $user = Auth::user();
            $credential = OtpServiceCredential::where('user_id', $user->id)
                ->where('service_id', $serviceId)
                ->with('service:id,name,fields')
                ->first();

            if (!$credential) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP service credential not found.',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP service credential retrieved successfully.',
                'data' => [
                    'id' => $credential->id,
                    'service_id' => $credential->service_id,
                    'service_name' => $credential->service->name,
                    'service_fields' => $credential->service->fields,
                    'credentials' => $credential->decrypted_credentials
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving OTP service credential.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'credentials' => 'required|array',
            ]);

            $user = Auth::user();
            $credential = OtpServiceCredential::where('id', $id)
                ->where('user_id', $user->id)
                ->with('service')
                ->first();

            if (!$credential) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP service credential not found.',
                ], 404);
            }

            // Build dynamic validation rules from service field definitions
            $validationRules = $this->buildValidationRules($credential->service->fields);
            
            // Build nested validation rules for credentials.*
            $credentialsRules = [];
            foreach ($validationRules as $fieldName => $rules) {
                $credentialsRules['credentials.' . $fieldName] = $rules;
            }
            
            // Validate credentials
            $validatedCredentials = $request->validate($credentialsRules);

            $credential->credentials = $request->input('credentials'); // Will be encrypted by mutator
            $credential->save();

            return response()->json([
                'success' => true,
                'message' => 'OTP service credential updated successfully.',
                'data' => [
                    'id' => $credential->id,
                    'service_id' => $credential->service_id,
                    'service_name' => $credential->service->name,
                    'credentials' => $credential->decrypted_credentials
                ]
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating OTP service credential.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $user = Auth::user();
            $credential = OtpServiceCredential::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$credential) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP service credential not found.',
                ], 404);
            }

            $credential->delete();

            return response()->json([
                'success' => true,
                'message' => 'OTP service credential deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting OTP service credential.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete all OTP service credentials for the authenticated user
     */
    public function destroyAll()
    {
        try {
            $user = Auth::user();
            $deleted = OtpServiceCredential::where('user_id', $user->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'All OTP service credentials deleted successfully.',
                'deleted_count' => $deleted
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting OTP service credentials.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build dynamic validation rules from service fields definition
     */
    private function buildValidationRules($fields)
    {
        $rules = [];
        
        if (!is_array($fields)) {
            return $rules;
        }
        
        foreach ($fields as $field) {
            $fieldRules = [];
            
            if ($field['required'] ?? false) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }
            
            // Default to string validation
            $fieldRules[] = 'string';
            
            // Max length if specified
            if (isset($field['max_length'])) {
                $fieldRules[] = 'max:' . $field['max_length'];
            }
            
            $rules[$field['name']] = implode('|', $fieldRules);
        }
        
        return $rules;
    }
}
