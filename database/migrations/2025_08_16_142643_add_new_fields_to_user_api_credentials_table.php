<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_api_credentials', function (Blueprint $table) {
            // Electra API credentials
            $table->string('electra_affid')->nullable()->after('aweber_list_id');
            $table->text('electra_api_key')->nullable()->after('electra_affid');

            // Add affid for Novelix
            $table->string('novelix_affid')->nullable()->after('novelix_api_key');

            // Add additional fields for ELPS
            $table->string('elps_ai')->nullable()->after('elps_api_key'); // ai parameter
            $table->string('elps_ci')->nullable()->after('elps_ai'); // ci parameter
            $table->string('elps_gi')->nullable()->after('elps_ci'); // gi parameter

            // Add additional fields for Tigloo
            $table->string('tigloo_ai')->nullable()->after('tigloo_api_key'); // ai parameter
            $table->string('tigloo_ci')->nullable()->after('tigloo_ai'); // ci parameter
            $table->string('tigloo_gi')->nullable()->after('tigloo_ci'); // gi parameter

            // Add additional fields for Dark
            $table->string('dark_ai')->nullable()->after('dark_api_key'); // ai parameter
            $table->string('dark_ci')->nullable()->after('dark_ai'); // ci parameter
            $table->string('dark_gi')->nullable()->after('dark_ci'); // gi parameter
        });
    }

    public function down(): void
    {
        Schema::table('user_api_credentials', function (Blueprint $table) {
            $table->dropColumn([
                'electra_affid',
                'electra_api_key',
                'novelix_affid',
                'elps_ai',
                'elps_ci',
                'elps_gi',
                'tigloo_ai',
                'tigloo_ci',
                'tigloo_gi',
                'dark_ai',
                'dark_ci',
                'dark_gi'
            ]);
        });
    }
};
