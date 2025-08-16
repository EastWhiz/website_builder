<?php

namespace App\Http\Controllers;

use App\Models\UserApiCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ApiCredentialsController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'aweber_client_id' => 'nullable|string|max:255',
                'aweber_client_secret' => 'nullable|string|max:255',
                'aweber_account_id' => 'nullable|string|max:255',
                'aweber_list_id' => 'nullable|string|max:255',
                'electra_affid' => 'nullable|string|max:255',
                'electra_api_key' => 'nullable|string|max:255',
                'dark_username' => 'nullable|string|max:255',
                'dark_password' => 'nullable|string|max:255',
                'dark_api_key' => 'nullable|string|max:255',
                'dark_ai' => 'nullable|string|max:255',
                'dark_ci' => 'nullable|string|max:255',
                'dark_gi' => 'nullable|string|max:255',
                'elps_username' => 'nullable|string|max:255',
                'elps_password' => 'nullable|string|max:255',
                'elps_api_key' => 'nullable|string|max:255',
                'elps_ai' => 'nullable|string|max:255',
                'elps_ci' => 'nullable|string|max:255',
                'elps_gi' => 'nullable|string|max:255',
                'meeseeks_api_key' => 'nullable|string|max:255',
                'novelix_api_key' => 'nullable|string|max:255',
                'novelix_affid' => 'nullable|string|max:255',
                'tigloo_username' => 'nullable|string|max:255',
                'tigloo_password' => 'nullable|string|max:255',
                'tigloo_api_key' => 'nullable|string|max:255',
                'tigloo_ai' => 'nullable|string|max:255',
                'tigloo_ci' => 'nullable|string|max:255',
                'tigloo_gi' => 'nullable|string|max:255',
            ]);

            $user = Auth::user();

            // Update or create API credentials for the authenticated user
            $credentials = UserApiCredential::updateOrCreate(
                ['user_id' => $user->id],
                array_merge($validated, ['user_id' => $user->id])
            );

            return response()->json([
                'success' => true,
                'message' => 'API credentials saved successfully.',
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
                'message' => 'An error occurred while saving credentials.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show()
    {
        try {
            $user = Auth::user();
            $credentials = UserApiCredential::where('user_id', $user->id)->first();

            if (!$credentials) {
                return response()->json([
                    'success' => true,
                    'message' => 'No API credentials found.',
                    'data' => null
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'API credentials retrieved successfully.',
                'data' => $credentials
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving credentials.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy()
    {
        try {
            $user = Auth::user();
            $deleted = UserApiCredential::where('user_id', $user->id)->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'API credentials deleted successfully.'
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No API credentials found to delete.'
                ], 404);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting credentials.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get API credentials for a specific provider
     */
    public function getProviderCredentials($provider)
    {
        try {
            $user = Auth::user();
            $credentials = UserApiCredential::where('user_id', $user->id)->first();

            if (!$credentials) {
                return response()->json([
                    'success' => false,
                    'message' => 'No API credentials found.',
                    'data' => null
                ], 404);
            }

            // Return only the credentials for the requested provider
            $providerData = [];
            switch ($provider) {
                case 'aweber':
                    $providerData = [
                        'client_id' => $credentials->aweber_client_id,
                        'client_secret' => $credentials->aweber_client_secret,
                        'account_id' => $credentials->aweber_account_id,
                        'list_id' => $credentials->aweber_list_id,
                    ];
                    break;
                case 'electra':
                    $providerData = [
                        'affid' => $credentials->electra_affid,
                        'api_key' => $credentials->electra_api_key,
                        'endpoint' => 'https://lcaapi.net/leads',
                    ];
                    break;
                case 'dark':
                    $providerData = [
                        'username' => $credentials->dark_username,
                        'password' => $credentials->dark_password,
                        'api_key' => $credentials->dark_api_key,
                        'ai' => $credentials->dark_ai,
                        'ci' => $credentials->dark_ci,
                        'gi' => $credentials->dark_gi,
                        'endpoint' => 'https://tb.connnecto.com/api/signup/procform',
                    ];
                    break;
                case 'elps':
                    $providerData = [
                        'username' => $credentials->elps_username,
                        'password' => $credentials->elps_password,
                        'api_key' => $credentials->elps_api_key,
                        'ai' => $credentials->elps_ai,
                        'ci' => $credentials->elps_ci,
                        'gi' => $credentials->elps_gi,
                        'endpoint' => 'https://ep.elpistrack.io/api/signup/procform',
                    ];
                    break;
                case 'meeseeks':
                    $providerData = [
                        'api_key' => $credentials->meeseeks_api_key,
                        'endpoint' => 'https://mskmd-api.com/api/v2/leads',
                    ];
                    break;
                case 'novelix':
                    $providerData = [
                        'api_key' => $credentials->novelix_api_key,
                        'affid' => $credentials->novelix_affid,
                        'endpoint' => 'https://nexlapi.net/leads',
                    ];
                    break;
                case 'tigloo':
                    $providerData = [
                        'username' => $credentials->tigloo_username,
                        'password' => $credentials->tigloo_password,
                        'api_key' => $credentials->tigloo_api_key,
                        'ai' => $credentials->tigloo_ai,
                        'ci' => $credentials->tigloo_ci,
                        'gi' => $credentials->tigloo_gi,
                        'endpoint' => 'https://platform.onlinepartnersed.com/api/signup/procform',
                    ];
                    break;
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid provider specified.'
                    ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Provider credentials retrieved successfully.',
                'data' => $providerData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving provider credentials.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
