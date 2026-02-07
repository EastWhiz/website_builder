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
}
