<?php

namespace App\Services;

use App\Models\ApiCategory;
use App\Models\UserApiCredential;
use App\Models\UserApiInstance;

class ApiCompatibilityService
{
    /** Provider string (e.g. elps, nauta) -> category name for lookup. */
    private const PROVIDER_TO_CATEGORY = [
        'elps' => 'Trackbox',
        'magicads' => 'Trackbox',
        'tigloo' => 'Trackbox',
        'pastile' => 'Trackbox',
        'newmedis' => 'Trackbox',
        'seamediaone' => 'Trackbox',
        'nauta' => 'Nauta',
        'irev' => 'iRev',
        'leadgreed' => 'LeadGreed',
        'getlinked' => 'GetLinked',
        'aweber' => 'AWeber',
        'electra' => 'Electra',
        'dark' => 'Dark',
        'meeseeks' => 'Meeseeksmedia',
        'novelix' => 'Novelix',
        'koi' => 'Koi',
        'riceleads' => 'Riceleads',
        'adzentric' => 'Adzentric',
    ];

    /** Per-provider: our field name => legacy column name. */
    private const PROVIDER_FIELD_TO_LEGACY = [
        'elps' => ['username' => 'elps_username', 'password' => 'elps_password', 'api_key' => 'elps_api_key', 'ai' => 'elps_ai', 'ci' => 'elps_ci', 'gi' => 'elps_gi'],
        'magicads' => ['username' => 'magicads_username', 'password' => 'magicads_password', 'api_key' => 'magicads_api_key', 'ai' => 'magicads_ai', 'ci' => 'magicads_ci', 'gi' => 'magicads_gi'],
        'tigloo' => ['username' => 'tigloo_username', 'password' => 'tigloo_password', 'api_key' => 'tigloo_api_key', 'ai' => 'tigloo_ai', 'ci' => 'tigloo_ci', 'gi' => 'tigloo_gi'],
        'pastile' => ['username' => 'pastile_username', 'password' => 'pastile_password', 'api_key' => 'pastile_api_key', 'ai' => 'pastile_ai', 'ci' => 'pastile_ci', 'gi' => 'pastile_gi'],
        'newmedis' => ['username' => 'newmedis_username', 'password' => 'newmedis_password', 'api_key' => 'newmedis_api_key', 'ai' => 'newmedis_ai', 'ci' => 'newmedis_ci', 'gi' => 'newmedis_gi'],
        'seamediaone' => ['username' => 'seamediaone_username', 'password' => 'seamediaone_password', 'api_key' => 'seamediaone_api_key', 'ai' => 'seamediaone_ai', 'ci' => 'seamediaone_ci', 'gi' => 'seamediaone_gi'],
        'dark' => ['username' => 'dark_username', 'password' => 'dark_password', 'api_key' => 'dark_api_key', 'ai' => 'dark_ai', 'ci' => 'dark_ci', 'gi' => 'dark_gi'],
        'nauta' => ['api_token' => 'nauta_api_token'],
        'irev' => ['api_token' => 'nauta_api_token'],
        'aweber' => ['client_id' => 'aweber_client_id', 'client_secret' => 'aweber_client_secret', 'account_id' => 'aweber_account_id', 'list_id' => 'aweber_list_id'],
        'electra' => ['affid' => 'electra_affid', 'api_key' => 'electra_api_key'],
        'meeseeks' => ['api_key' => 'meeseeks_api_key'],
        'novelix' => ['api_key' => 'novelix_api_key', 'affid' => 'novelix_affid'],
        'koi' => ['api_key' => 'koi_api_key'],
        'riceleads' => ['api_key' => 'riceleads_api_key', 'affid' => 'riceleads_affid'],
        'adzentric' => ['affid' => 'adzentric_affid', 'api_key' => 'adzentric_api_key'],
    ];

    /**
     * Get credentials for a provider in legacy format.
     * Tries new structure (UserApiInstance) first, then falls back to UserApiCredential.
     *
     * @return UserApiCredential|\stdClass|null Legacy credential model, legacy-shaped object, or null.
     */
    public function getLegacyCredentials(int $userId, string $provider): UserApiCredential|\stdClass|null
    {
        $provider = strtolower(trim($provider));
        $categoryName = self::PROVIDER_TO_CATEGORY[$provider] ?? null;

        if ($categoryName) {
            $category = ApiCategory::where('name', $categoryName)->first();
            if ($category) {
                $instance = UserApiInstance::where('user_id', $userId)
                    ->where('api_category_id', $category->id)
                    ->where('is_active', true)
                    ->with(['category.fields', 'values.field'])
                    ->first();
                if ($instance) {
                    return $this->convertToLegacyFormat($instance, $provider);
                }
            }
        }

        return UserApiCredential::where('user_id', $userId)->first();
    }

    /**
     * Convert a UserApiInstance to an object with legacy UserApiCredential property names.
     */
    public function convertToLegacyFormat(UserApiInstance $instance, string $provider): object
    {
        $provider = strtolower(trim($provider));
        $credentials = $instance->credentials;

        $legacy = new \stdClass;
        foreach ((new UserApiCredential)->getFillable() as $key) {
            if ($key !== 'user_id') {
                $legacy->{$key} = '';
            }
        }

        $fieldMap = self::PROVIDER_FIELD_TO_LEGACY[$provider] ?? [];
        foreach ($fieldMap as $ourField => $legacyColumn) {
            $value = $credentials[$ourField] ?? '';
            if (isset($legacy->{$legacyColumn})) {
                $legacy->{$legacyColumn} = $value;
            }
        }

        $legacy->user_id = $instance->user_id;
        return $legacy;
    }
}
