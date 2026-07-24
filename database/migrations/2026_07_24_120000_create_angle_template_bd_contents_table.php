<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add structured BD storage without changing legacy rendered pages.
     */
    public function up(): void
    {
        if (Schema::hasTable('angle_templates')) {
            Schema::table('angle_templates', function (Blueprint $table) {
                if (! Schema::hasColumn('angle_templates', 'content_mode')) {
                    $table->string('content_mode', 32)->default('legacy')->after('main_js');
                }

                if (! Schema::hasColumn('angle_templates', 'structured_version')) {
                    $table->unsignedSmallInteger('structured_version')->nullable()->after('content_mode');
                }
            });
        }

        if (! Schema::hasTable('angle_template_bd_contents')) {
            Schema::create('angle_template_bd_contents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('angle_template_id');
                $table->uuid('angle_template_uuid');
                $table->string('parent_bd', 16);
                $table->string('slot_key', 64);
                $table->string('slot_type', 32)->default('html');
                $table->longText('content')->nullable();
                $table->unsignedInteger('sort')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['angle_template_id', 'slot_key'], 'at_bd_contents_template_slot_unique');
                $table->index('angle_template_uuid', 'at_bd_contents_template_uuid_idx');
                $table->index(['parent_bd', 'slot_key'], 'at_bd_contents_parent_slot_idx');
            });
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        if (Schema::hasTable('angle_template_bd_contents')) {
            Schema::dropIfExists('angle_template_bd_contents');
        }

        if (Schema::hasTable('angle_templates')) {
            Schema::table('angle_templates', function (Blueprint $table) {
                if (Schema::hasColumn('angle_templates', 'structured_version')) {
                    $table->dropColumn('structured_version');
                }

                if (Schema::hasColumn('angle_templates', 'content_mode')) {
                    $table->dropColumn('content_mode');
                }
            });
        }
    }
};
