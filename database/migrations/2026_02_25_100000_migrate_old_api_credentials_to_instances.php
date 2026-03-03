<?php

use App\Models\ApiCategory;
use App\Models\ApiCategoryField;
use App\Models\UserApiCredential;
use App\Models\UserApiInstance;
use App\Models\UserApiInstanceValue;
use Database\Seeders\ApiMigrationMappingSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migrates legacy user_api_credentials into UserApiInstance + UserApiInstanceValue.
     */
    public function up(): void
    {
        Schema::table('user_api_credentials', function (Blueprint $table) {
            $table->timestamp('migrated_at')->nullable()->after('updated_at');
        });

        $mapping = ApiMigrationMappingSeeder::getMapping();
        $credentials = UserApiCredential::whereNull('migrated_at')->get();

        foreach ($credentials as $cred) {
            $this->migrateUserCredentials($cred, $mapping);
        }
    }

    /**
     * Migrate one user's credential row to instances per provider.
     */
    private function migrateUserCredentials(UserApiCredential $cred, array $mapping): void
    {
        foreach ($mapping as $provider => $config) {
            $categoryName = $config['category_name'] ?? null;
            $fields = $config['fields'] ?? [];
            if (!$categoryName || empty($fields)) {
                continue;
            }

            $category = ApiCategory::where('name', $categoryName)->first();
            if (!$category) {
                continue;
            }

            $hasData = false;
            foreach (array_keys($fields) as $oldCol) {
                $val = $cred->getAttribute($oldCol);
                if ($val !== null && $val !== '') {
                    $hasData = true;
                    break;
                }
            }
            if (!$hasData) {
                continue;
            }

            $instance = UserApiInstance::create([
                'user_id' => $cred->user_id,
                'api_category_id' => $category->id,
                'name' => ucfirst($provider),
                'is_active' => true,
            ]);

            $category->load('fields');

            // Seed endpoint_url from legacy hardcoded endpoints, if this category supports it.
            $endpointUrl = $this->getEndpointUrlForProvider($provider);
            if ($endpointUrl !== null) {
                $endpointField = $category->fields->firstWhere('name', 'endpoint_url');
                if ($endpointField) {
                    $endpointRecord = new UserApiInstanceValue();
                    $endpointRecord->user_api_instance_id = $instance->id;
                    $endpointRecord->api_category_field_id = $endpointField->id;
                    $endpointRecord->setRelation('field', $endpointField);
                    $endpointRecord->value = $endpointUrl;
                    $endpointRecord->save();
                }
            }

            foreach ($fields as $oldCol => $newFieldName) {
                $value = $cred->getAttribute($oldCol);
                if ($value === null || $value === '') {
                    continue;
                }

                $field = $category->fields->firstWhere('name', $newFieldName);
                if (!$field) {
                    continue;
                }

                $record = new UserApiInstanceValue();
                $record->user_api_instance_id = $instance->id;
                $record->api_category_field_id = $field->id;
                $record->setRelation('field', $field);
                $record->value = $value;
                $record->save();
            }
        }

        $cred->migrated_at = now();
        $cred->save();
    }

    /**
     * Get the legacy hardcoded endpoint URL for a given provider, if any.
     */
    private function getEndpointUrlForProvider(string $provider): ?string
    {
        $map = [
            // Trackbox providers
            'elps' => 'https://ep.elpistrack.io/api/signup/procform',
            'magicads' => 'https://mb.magicadsoffers.com/api/signup/procform',
            'newmedis' => 'https://tb.newmedis.live/api/signup/procform',
            'pastile' => 'https://tb.pastile.net/api/signup/procform',
            'seamediaone' => 'https://tb.seamediaone.net/api/signup/procform',
            'dark' => 'https://tb.connnecto.com/api/signup/procform',
            'tigloo' => 'https://platform.onlinepartnersed.com/api/signup/procform',

            // iRev (Nauta) provider
            'nauta' => 'https://yourleads.org/api/affiliates/v2/leads',

            // LeadGreed providers
            'electra' => 'https://lcaapi.net/leads',
            'riceleads' => 'https://ridapi.net/leads',
            'adzentric' => 'https://ldlgapi.com/leads',

            // GetLinked providers
            'koi' => 'https://hannyaapi.com/api/v2/leads',
            'meeseeks' => 'https://mskmd-api.com/api/v2/leads',
        ];

        return $map[$provider] ?? null;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_api_credentials', function (Blueprint $table) {
            $table->dropColumn('migrated_at');
        });
    }
};
