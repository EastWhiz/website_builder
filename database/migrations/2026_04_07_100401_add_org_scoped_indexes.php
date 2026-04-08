<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add org-scoped indexes safely (idempotent).
     *
     * This is split out to avoid failing the column-add migration (100400)
     * when a DB is partially migrated.
     */
    public function up(): void
    {
        // Add simple indexes; ignore if they already exist.
        $this->tryAddIndex('angles', 'angles_organization_id_index', 'organization_id');
        $this->tryAddIndex('angle_templates', 'angle_templates_organization_id_index', 'organization_id');
        $this->tryAddIndex('thank_you_pages', 'thank_you_pages_organization_id_index', 'organization_id');
        $this->tryAddIndex('user_api_instances', 'user_api_instances_organization_id_index', 'organization_id');
    }

    public function down(): void
    {
        $this->tryDropIndex('user_api_instances', 'user_api_instances_organization_id_index');
        $this->tryDropIndex('thank_you_pages', 'thank_you_pages_organization_id_index');
        $this->tryDropIndex('angle_templates', 'angle_templates_organization_id_index');
        $this->tryDropIndex('angles', 'angles_organization_id_index');
    }

    private function tryAddIndex(string $table, string $indexName, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }
        try {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)");
        } catch (\Throwable $e) {
            // Ignore duplicate index / already exists / engine quirks.
        }
    }

    private function tryDropIndex(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        try {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        } catch (\Throwable $e) {
            // Ignore if missing.
        }
    }
};

