<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Mapping from legacy UserApiCredential (per-provider columns) to new structure:
 * 5 categories only: Trackbox, iRev, LeadGreed, GetLinked, Aweber.
 * Used by the data migration in Phase 7.2.
 */
class ApiMigrationMappingSeeder extends Seeder
{
    public function run(): void
    {
        // Mapping is defined in getMapping(). Run is a no-op.
    }

    /**
     * Get migration mapping: provider => category_name + old column => new field name.
     *
     * @return array<string, array{category_name: string, fields: array<string, string>}>
     */
    public static function getMapping(): array
    {
        return [
            // Trackbox: ELPS, MagicAds, NewMedis, Pastile, SeaMediaOne, Dark, Tigloo
            'elps' => [
                'category_name' => 'Trackbox',
                'fields' => [
                    'elps_username' => 'username',
                    'elps_password' => 'password',
                    'elps_api_key' => 'api_key',
                    'elps_ai' => 'ai',
                    'elps_ci' => 'ci',
                    'elps_gi' => 'gi',
                ],
            ],
            'magicads' => [
                'category_name' => 'Trackbox',
                'fields' => [
                    'magicads_username' => 'username',
                    'magicads_password' => 'password',
                    'magicads_api_key' => 'api_key',
                    'magicads_ai' => 'ai',
                    'magicads_ci' => 'ci',
                    'magicads_gi' => 'gi',
                ],
            ],
            'newmedis' => [
                'category_name' => 'Trackbox',
                'fields' => [
                    'newmedis_username' => 'username',
                    'newmedis_password' => 'password',
                    'newmedis_api_key' => 'api_key',
                    'newmedis_ai' => 'ai',
                    'newmedis_ci' => 'ci',
                    'newmedis_gi' => 'gi',
                ],
            ],
            'pastile' => [
                'category_name' => 'Trackbox',
                'fields' => [
                    'pastile_username' => 'username',
                    'pastile_password' => 'password',
                    'pastile_api_key' => 'api_key',
                    'pastile_ai' => 'ai',
                    'pastile_ci' => 'ci',
                    'pastile_gi' => 'gi',
                ],
            ],
            'seamediaone' => [
                'category_name' => 'Trackbox',
                'fields' => [
                    'seamediaone_username' => 'username',
                    'seamediaone_password' => 'password',
                    'seamediaone_api_key' => 'api_key',
                    'seamediaone_ai' => 'ai',
                    'seamediaone_ci' => 'ci',
                    'seamediaone_gi' => 'gi',
                ],
            ],
            'dark' => [
                'category_name' => 'Trackbox',
                'fields' => [
                    'dark_username' => 'username',
                    'dark_password' => 'password',
                    'dark_api_key' => 'api_key',
                    'dark_ai' => 'ai',
                    'dark_ci' => 'ci',
                    'dark_gi' => 'gi',
                ],
            ],
            'tigloo' => [
                'category_name' => 'Trackbox',
                'fields' => [
                    'tigloo_username' => 'username',
                    'tigloo_password' => 'password',
                    'tigloo_api_key' => 'api_key',
                    'tigloo_ai' => 'ai',
                    'tigloo_ci' => 'ci',
                    'tigloo_gi' => 'gi',
                ],
            ],
            // iRev: Nauta
            'nauta' => [
                'category_name' => 'iRev',
                'fields' => [
                    'nauta_api_token' => 'api_token',
                ],
            ],
            // LeadGreed: Electra, Riceleads, AdZentric
            'electra' => [
                'category_name' => 'LeadGreed',
                'fields' => [
                    'electra_affid' => 'affid',
                    'electra_api_key' => 'api_key',
                ],
            ],
            'riceleads' => [
                'category_name' => 'LeadGreed',
                'fields' => [
                    'riceleads_api_key' => 'api_key',
                    'riceleads_affid' => 'affid',
                ],
            ],
            'adzentric' => [
                'category_name' => 'LeadGreed',
                'fields' => [
                    'adzentric_affid' => 'affid',
                    'adzentric_api_key' => 'api_key',
                ],
            ],
            // GetLinked: Koi, MeeseekMedia
            'koi' => [
                'category_name' => 'GetLinked',
                'fields' => [
                    'koi_api_key' => 'api_key',
                ],
            ],
            'meeseeks' => [
                'category_name' => 'GetLinked',
                'fields' => [
                    'meeseeks_api_key' => 'api_key',
                ],
            ],
            // Aweber: Aweber
            'aweber' => [
                'category_name' => 'Aweber',
                'fields' => [
                    'aweber_client_id' => 'client_id',
                    'aweber_client_secret' => 'client_secret',
                    'aweber_account_id' => 'account_id',
                    'aweber_list_id' => 'list_id',
                ],
            ],
        ];
    }
}
