<?php

namespace App\Http\Controllers;

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
                ->orderBy('created_at', 'desc')
                ->get();

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
                'service_name' => 'required|string|max:255',
                'access_key' => 'nullable|string|max:255',
                'endpoint_url' => 'nullable|url|max:1000',
            ]);

            $user = Auth::user();

            // Update or create OTP service credentials for the authenticated user
            // Using updateOrCreate to handle both create and update scenarios
            $credentials = OtpServiceCredential::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'service_name' => $validated['service_name']
                ],
                [
                    'user_id' => $user->id,
                    'service_name' => $validated['service_name'],
                    'access_key' => $validated['access_key'] ?? null,
                    'endpoint_url' => $validated['endpoint_url'] ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'OTP service credentials saved successfully.',
                'data' => $credentials
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
                'data' => $credential
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
     * Get OTP service credentials by service name
     */
    public function getByServiceName(string $serviceName)
    {
        try {
            $user = Auth::user();
            $credential = OtpServiceCredential::where('user_id', $user->id)
                ->where('service_name', $serviceName)
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
                'data' => $credential
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
                'service_name' => 'sometimes|required|string|max:255',
                'access_key' => 'nullable|string|max:255',
                'endpoint_url' => 'nullable|url|max:1000',
            ]);

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

            // Check if service_name is being changed and if it conflicts with existing record
            if (isset($validated['service_name']) && $validated['service_name'] !== $credential->service_name) {
                $existing = OtpServiceCredential::where('user_id', $user->id)
                    ->where('service_name', $validated['service_name'])
                    ->where('id', '!=', $id)
                    ->first();

                if ($existing) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A credential for this service already exists.',
                    ], 422);
                }
            }

            $credential->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'OTP service credential updated successfully.',
                'data' => $credential
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
}
