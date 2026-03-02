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
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_api_credentials', function (Blueprint $table) {
            $table->dropColumn('migrated_at');
        });
    }
};
