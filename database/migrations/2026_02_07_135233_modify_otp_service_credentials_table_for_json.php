<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('otp_service_credentials', function (Blueprint $table) {
            // Drop old unique constraint first
            $table->dropUnique(['user_id', 'service_name']);
            
            // Add service_id column (nullable initially for data migration)
            $table->unsignedBigInteger('service_id')->nullable()->after('user_id');
            
            // Add credentials JSON column
            $table->json('credentials')->nullable()->after('service_id');
            
            // Add index for service_id
            $table->index('service_id');
        });
        
        // Migrate existing data to new structure
        // Note: This assumes otp_services table has been created and seeded first
        // Only migrate if there are existing records
        if (Schema::hasTable('otp_services')) {
            $existingCredentials = DB::table('otp_service_credentials')
                ->whereNotNull('service_name')
                ->get();
            
            foreach ($existingCredentials as $credential) {
                // Find matching service by name
                $service = DB::table('otp_services')
                    ->where('name', $credential->service_name)
                    ->first();
                
                if ($service) {
                    // Build credentials JSON
                    $credentialsJson = [];
                    if ($credential->access_key) {
                        $credentialsJson['access_key'] = $credential->access_key;
                    }
                    if ($credential->endpoint_url) {
                        $credentialsJson['endpoint_url'] = $credential->endpoint_url;
                    }
                    
                    // Update the record
                    DB::table('otp_service_credentials')
                        ->where('id', $credential->id)
                        ->update([
                            'service_id' => $service->id,
                            'credentials' => json_encode($credentialsJson)
                        ]);
                }
            }
        }
        
        // Now add foreign key and unique constraint
        Schema::table('otp_service_credentials', function (Blueprint $table) {
            $table->foreign('service_id')->references('id')->on('otp_services')->onDelete('cascade');
            $table->unique(['user_id', 'service_id']);
        });
        
        // Drop old columns after data migration
        Schema::table('otp_service_credentials', function (Blueprint $table) {
            $table->dropColumn(['service_name', 'access_key', 'endpoint_url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('otp_service_credentials', function (Blueprint $table) {
            // Re-add old columns
            $table->string('service_name', 50)->nullable()->after('user_id');
            $table->text('access_key')->nullable()->after('service_name');
            $table->text('endpoint_url')->nullable()->after('access_key');
            
            // Drop new structure
            $table->dropForeign(['service_id']);
            $table->dropUnique(['user_id', 'service_id']);
            $table->dropIndex(['service_id']);
            $table->dropColumn(['service_id', 'credentials']);
            
            // Re-add old unique constraint
            $table->unique(['user_id', 'service_name']);
        });
    }
};
