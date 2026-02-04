<?php

namespace App\Http\Controllers;

use App\Models\UserApiCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ApiCredentialsController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'provider' => 'required|string|in:aweber,dark,electra,elps,meeseeks,novelix,tigloo,koi,pastile,riceleads,newmedis,seamediaone,nauta,facebook,second',
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
                'facebook_pixel_url' => 'nullable|url|max:1000',
                'second_pixel_url' => 'nullable|url|max:1000',
                'koi_api_key' => 'nullable|string|max:255',
                'pastile_username' => 'nullable|string|max:255',
                'pastile_password' => 'nullable|string|max:255',
                'pastile_api_key' => 'nullable|string|max:255',
                'pastile_ai' => 'nullable|string|max:255',
                'pastile_ci' => 'nullable|string|max:255',
                'pastile_gi' => 'nullable|string|max:255',
                'riceleads_api_key' => 'nullable|string|max:255',
                'riceleads_affid' => 'nullable|string|max:255',
                'newmedis_username' => 'nullable|string|max:255',
                'newmedis_password' => 'nullable|string|max:255',
                'newmedis_api_key' => 'nullable|string|max:255',
                'newmedis_ai' => 'nullable|string|max:255',
                'newmedis_ci' => 'nullable|string|max:255',
                'newmedis_gi' => 'nullable|string|max:255',
                'seamediaone_username' => 'nullable|string|max:255',
                'seamediaone_password' => 'nullable|string|max:255',
                'seamediaone_api_key' => 'nullable|string|max:255',
                'seamediaone_ai' => 'nullable|string|max:255',
                'seamediaone_ci' => 'nullable|string|max:255',
                'seamediaone_gi' => 'nullable|string|max:255',
                'nauta_api_token' => 'nullable|string|max:255',
            ]);

            $user = Auth::user();
            $provider = $validated['provider'];

            // Remove provider from validated data as it's not a database field
            unset($validated['provider']);

            // Update or create API credentials for the authenticated user
            $credentials = UserApiCredential::updateOrCreate(
                ['user_id' => $user->id],
                array_merge($validated, ['user_id' => $user->id])
            );

            // Call external API to sync the specific provider's credentials
            // Skip syncing if running on localhost
            $host = request()->getHost();
            if ($host !== 'localhost' && $host !== '127.0.0.1') {
                $this->syncToExternalApi($credentials, $provider, $user->id);
            }

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
                case 'koi':
                    $providerData = [
                        'api_key' => $credentials->koi_api_key,
                        'endpoint' => 'https://hannyaapi.com/api/v2/leads',
                    ];
                    break;
                case 'pastile':
                    $providerData = [
                        'username' => $credentials->pastile_username,
                        'password' => $credentials->pastile_password,
                        'api_key' => $credentials->pastile_api_key,
                        'ai' => $credentials->pastile_ai,
                        'ci' => $credentials->pastile_ci,
                        'gi' => $credentials->pastile_gi,
                        'endpoint' => 'https://tb.pastile.net/api/signup/procform',
                    ];
                    break;
                case 'newmedis':
                    $providerData = [
                        'username' => $credentials->newmedis_username,
                        'password' => $credentials->newmedis_password,
                        'api_key' => $credentials->newmedis_api_key,
                        'ai' => $credentials->newmedis_ai,
                        'ci' => $credentials->newmedis_ci,
                        'gi' => $credentials->newmedis_gi,
                        'endpoint' => 'https://tb.newmedis.live/api/signup/procform',
                    ];
                    break;
                case 'seamediaone':
                    $providerData = [
                        'username' => $credentials->seamediaone_username,
                        'password' => $credentials->seamediaone_password,
                        'api_key' => $credentials->seamediaone_api_key,
                        'ai' => $credentials->seamediaone_ai,
                        'ci' => $credentials->seamediaone_ci,
                        'gi' => $credentials->seamediaone_gi,
                        'endpoint' => 'https://tb.seamediaone.net/api/signup/procform',
                    ];
                    break;
                    case 'riceleads':
                        $providerData = [
                            'affid' => $credentials->riceleads_affid,
                            'api_key' => $credentials->riceleads_api_key,
                        'endpoint' => 'https://ridapi.net/leads',
                        ];
                    break;
                case 'nauta':
                    $providerData = [
                        'api_token' => $credentials->nauta_api_token,
                        'endpoint' => 'https://yourleads.org/api/affiliates/v2/leads',
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

    /**
     * Sync provider credentials to external API
     */
    private function syncToExternalApi($credentials, $provider, $userId)
    {
        try {
            $apiPayload = $this->buildApiPayload($credentials, $provider, $userId);

            $response = Http::post('https://crm.diy/api/v1/create-update-api-data', $apiPayload);

            // logger(json_encode($response->json()));

            if (!$response->successful()) {
                Log::error('External API sync failed', [
                    'provider' => $provider,
                    'user_id' => $userId,
                    'response' => $response->json()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('External API sync exception', [
                'provider' => $provider,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Build API payload for external API based on provider
     */
    private function buildApiPayload($credentials, $provider, $userId)
    {
        // Base payload with all empty fields
        $payload = [
            'apiType' => $provider,
            'clientId' => '',
            'clientSecret' => '',
            'accountId' => '',
            'listId' => '',
            'userName' => '',
            'password' => '',
            'apiKey' => '',
            'aiParam' => '',
            'ciParam' => '',
            'giParam' => '',
            'webBuilderUserId' => (string) ("U" . $userId),
            'affiliateId' => '',
            'endpointUrl' => ''
        ];

        // Map provider-specific fields
        switch ($provider) {
            case 'aweber':
                $payload['clientId'] = $credentials->aweber_client_id ?? '';
                $payload['clientSecret'] = $credentials->aweber_client_secret ?? '';
                $payload['accountId'] = $credentials->aweber_account_id ?? '';
                $payload['listId'] = $credentials->aweber_list_id ?? '';
                break;

            case 'electra':
                $payload['affiliateId'] = $credentials->electra_affid ?? '';
                $payload['apiKey'] = $credentials->electra_api_key ?? '';
                $payload['endpointUrl'] = 'https://lcaapi.net/leads';
                break;

            case 'dark':
                $payload['userName'] = $credentials->dark_username ?? '';
                $payload['password'] = $credentials->dark_password ?? '';
                $payload['apiKey'] = $credentials->dark_api_key ?? '';
                $payload['aiParam'] = $credentials->dark_ai ?? '';
                $payload['ciParam'] = $credentials->dark_ci ?? '';
                $payload['giParam'] = $credentials->dark_gi ?? '';
                $payload['endpointUrl'] = 'https://tb.connnecto.com/api/signup/procform';
                break;

            case 'elps':
                $payload['userName'] = $credentials->elps_username ?? '';
                $payload['password'] = $credentials->elps_password ?? '';
                $payload['apiKey'] = $credentials->elps_api_key ?? '';
                $payload['aiParam'] = $credentials->elps_ai ?? '';
                $payload['ciParam'] = $credentials->elps_ci ?? '';
                $payload['giParam'] = $credentials->elps_gi ?? '';
                $payload['endpointUrl'] = 'https://ep.elpistrack.io/api/signup/procform';
                break;

            case 'meeseeks':
                $payload['apiKey'] = $credentials->meeseeks_api_key ?? '';
                $payload['endpointUrl'] = 'https://mskmd-api.com/api/v2/leads';
                break;

            case 'novelix':
                $payload['apiKey'] = $credentials->novelix_api_key ?? '';
                $payload['affiliateId'] = $credentials->novelix_affid ?? '';
                $payload['endpointUrl'] = 'https://nexlapi.net/leads';
                break;

            case 'tigloo':
                $payload['userName'] = $credentials->tigloo_username ?? '';
                $payload['password'] = $credentials->tigloo_password ?? '';
                $payload['apiKey'] = $credentials->tigloo_api_key ?? '';
                $payload['aiParam'] = $credentials->tigloo_ai ?? '';
                $payload['ciParam'] = $credentials->tigloo_ci ?? '';
                $payload['giParam'] = $credentials->tigloo_gi ?? '';
                $payload['endpointUrl'] = 'https://platform.onlinepartnersed.com/api/signup/procform';
                break;

            case 'koi':
                $payload['apiKey'] = $credentials->koi_api_key ?? '';
                $payload['endpointUrl'] = 'https://hannyaapi.com/api/v2/leads';
                break;

            case 'pastile':
                $payload['userName'] = $credentials->pastile_username ?? '';
                $payload['password'] = $credentials->pastile_password ?? '';
                $payload['apiKey'] = $credentials->pastile_api_key ?? '';
                $payload['aiParam'] = $credentials->pastile_ai ?? '';
                $payload['ciParam'] = $credentials->pastile_ci ?? '';
                $payload['giParam'] = $credentials->pastile_gi ?? '';
                $payload['endpointUrl'] = 'https://tb.pastile.net/api/signup/procform';
                break;
            case 'newmedis':
                $payload['userName'] = $credentials->newmedis_username ?? '';
                $payload['password'] = $credentials->newmedis_password ?? '';
                $payload['apiKey'] = $credentials->newmedis_api_key ?? '';
                $payload['aiParam'] = $credentials->newmedis_ai ?? '';
                $payload['ciParam'] = $credentials->newmedis_ci ?? '';
                $payload['giParam'] = $credentials->newmedis_gi ?? '';
                $payload['endpointUrl'] = 'https://tb.newmedis.live/api/signup/procform';
                break;
            case 'seamediaone':
                $payload['userName'] = $credentials->seamediaone_username ?? '';
                $payload['password'] = $credentials->seamediaone_password ?? '';
                $payload['apiKey'] = $credentials->seamediaone_api_key ?? '';
                $payload['aiParam'] = $credentials->seamediaone_ai ?? '';
                $payload['ciParam'] = $credentials->seamediaone_ci ?? '';
                $payload['giParam'] = $credentials->seamediaone_gi ?? '';
                $payload['endpointUrl'] = 'https://tb.seamediaone.net/api/signup/procform';
                break;
            case 'riceleads':
                $payload['affiliateId'] = $credentials->riceleads_affid ?? '';
                $payload['apiKey'] = $credentials->riceleads_api_key ?? '';
                $payload['endpointUrl'] = 'https://ridapi.net/leads';
                break;
            case 'nauta':
                $payload['apiKey'] = $credentials->nauta_api_token ?? '';
                $payload['endpointUrl'] = 'https://yourleads.org/api/affiliates/v2/leads';
                break;
        }

        return $payload;
    }
}
