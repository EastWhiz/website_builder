<?php

namespace Database\Seeders;

use App\Models\ApiCategory;
use App\Models\ApiCategoryField;
use Illuminate\Database\Seeder;

/**
 * Seeds the 5 API categories (platforms) and their field definitions.
 * Category names match integration file names (e.g. Trackbox → trackbox.php).
 *
 * Categories and API instances:
 * - Trackbox: ELPS, MagicAds, NewMedis, Pastile, SeaMediaOne
 * - iRev: Nauta
 * - LeadGreed: Electra, Riceleads, AdZentric
 * - GetLinked: Koi, MeeseekMedia
 * - Aweber: Aweber
 */
class ApiCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Trackbox',
                'is_active' => true,
                'sort_order' => 1,
                'fields' => [
                    ['name' => 'endpoint_url', 'label' => 'Endpoint URL', 'type' => 'url', 'placeholder' => 'https://...', 'is_required' => true, 'encrypt' => false],
                    ['name' => 'username', 'label' => 'Username', 'type' => 'text', 'placeholder' => '', 'is_required' => false, 'encrypt' => false],
                    ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'placeholder' => '', 'is_required' => false, 'encrypt' => true],
                    ['name' => 'api_key', 'label' => 'API Key', 'type' => 'text', 'placeholder' => '', 'is_required' => false, 'encrypt' => true],
                    ['name' => 'ai', 'label' => 'AI', 'type' => 'text', 'placeholder' => '', 'is_required' => false, 'encrypt' => false],
                    ['name' => 'ci', 'label' => 'CI', 'type' => 'text', 'placeholder' => '', 'is_required' => false, 'encrypt' => false],
                    ['name' => 'gi', 'label' => 'GI', 'type' => 'text', 'placeholder' => '', 'is_required' => false, 'encrypt' => false],
                ],
            ],
            [
                'name' => 'iRev',
                'is_active' => true,
                'sort_order' => 2,
                'fields' => [
                    ['name' => 'endpoint_url', 'label' => 'Endpoint URL', 'type' => 'url', 'placeholder' => 'https://...', 'is_required' => true, 'encrypt' => false],
                    ['name' => 'api_token', 'label' => 'API Token', 'type' => 'text', 'placeholder' => '', 'is_required' => false, 'encrypt' => true],
                ],
            ],
            [
                'name' => 'LeadGreed',
                'is_active' => true,
                'sort_order' => 3,
                'fields' => [
                    ['name' => 'endpoint_url', 'label' => 'Endpoint URL', 'type' => 'url', 'placeholder' => 'https://...', 'is_required' => false, 'encrypt' => false],
                    ['name' => 'api_key', 'label' => 'API Key', 'type' => 'text', 'placeholder' => '', 'is_required' => false, 'encrypt' => true],
                    ['name' => 'affid', 'label' => 'Affiliate ID', 'type' => 'text', 'placeholder' => '', 'is_required' => false, 'encrypt' => false],
                ],
            ],
            [
                'name' => 'GetLinked',
                'is_active' => true,
                'sort_order' => 4,
                'fields' => [
                    ['name' => 'endpoint_url', 'label' => 'Endpoint URL', 'type' => 'url', 'placeholder' => 'https://...', 'is_required' => false, 'encrypt' => false],
                    ['name' => 'api_key', 'label' => 'API Key', 'type' => 'text', 'placeholder' => '', 'is_required' => false, 'encrypt' => true],
                ],
            ],
            [
                'name' => 'Aweber',
                'is_active' => true,
                'sort_order' => 5,
                'fields' => [
                    ['name' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'placeholder' => '', 'is_required' => false, 'encrypt' => false],
                    ['name' => 'client_secret', 'label' => 'Client Secret', 'type' => 'text', 'placeholder' => '', 'is_required' => false, 'encrypt' => true],
                    ['name' => 'account_id', 'label' => 'Account ID', 'type' => 'text', 'placeholder' => '', 'is_required' => false, 'encrypt' => false],
                    ['name' => 'list_id', 'label' => 'List ID', 'type' => 'text', 'placeholder' => '', 'is_required' => false, 'encrypt' => false],
                ],
            ],
        ];

        foreach ($categories as $data) {
            $fields = $data['fields'];
            unset($data['fields']);

            $category = ApiCategory::firstOrCreate(
                ['name' => $data['name']],
                [
                    'is_active' => $data['is_active'],
                    'sort_order' => $data['sort_order'],
                ]
            );

            foreach ($fields as $fieldData) {
                ApiCategoryField::firstOrCreate(
                    [
                        'api_category_id' => $category->id,
                        'name' => $fieldData['name'],
                    ],
                    [
                        'label' => $fieldData['label'],
                        'type' => $fieldData['type'],
                        'placeholder' => $fieldData['placeholder'] ?? null,
                        'is_required' => $fieldData['is_required'],
                        'encrypt' => $fieldData['encrypt'],
                    ]
                );
            }
        }
    }
}
