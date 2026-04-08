<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add org foreign keys only when safe (InnoDB).
     *
     * This avoids live-server errors on MyISAM tables. If tables are not InnoDB,
     * this migration becomes a no-op.
     */
    public function up(): void
    {
        if (!$this->isInnoDb('organizations')) {
            return;
        }

        $targets = ['angles', 'angle_templates', 'thank_you_pages', 'user_api_instances'];
        foreach ($targets as $table) {
            if (!$this->isInnoDb($table)) {
                continue;
            }
            if (!Schema::hasColumn($table, 'organization_id')) {
                continue;
            }

            $fkName = "{$table}_organization_id_foreign";
            try {
                DB::statement(
                    "ALTER TABLE `{$table}` " .
                    "ADD CONSTRAINT `{$fkName}` FOREIGN KEY (`organization_id`) " .
                    "REFERENCES `organizations` (`id`) ON DELETE SET NULL"
                );
            } catch (\Throwable $e) {
                // Ignore if already exists or cannot be added.
            }
        }
    }

    public function down(): void
    {
        $targets = ['user_api_instances', 'thank_you_pages', 'angle_templates', 'angles'];
        foreach ($targets as $table) {
            $fkName = "{$table}_organization_id_foreign";
            try {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            } catch (\Throwable $e) {
                // Ignore if missing.
            }
        }
    }

    private function isInnoDb(string $table): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        try {
            $row = DB::selectOne(
                "SELECT ENGINE as engine " .
                "FROM information_schema.TABLES " .
                "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$table]
            );
            return isset($row->engine) && strtolower((string) $row->engine) === 'innodb';
        } catch (\Throwable $e) {
            return false;
        }
    }
};

