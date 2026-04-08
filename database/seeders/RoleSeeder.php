<?php

namespace Database\Seeders;

use App\Models\Role;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Preserve existing platform roles used by legacy role_id checks.
        Role::updateOrCreate(
            ['name' => 'admin'],
            [
                'scope' => 'platform',
                'key' => 'admin',
                'description' => 'Platform super admin role',
                'is_system' => true,
                'is_active' => true,
            ]
        );

        Role::updateOrCreate(
            ['name' => 'member'],
            [
                'scope' => 'platform',
                'key' => 'member',
                'description' => 'Platform member role',
                'is_system' => true,
                'is_active' => true,
            ]
        );

        // Organization roles (assignable within organization_user.role_id).
        Role::updateOrCreate(
            ['scope' => 'organization', 'key' => 'org_admin'],
            [
                'name' => 'Org Admin',
                'description' => 'Organization administrator',
                'is_system' => true,
                'is_active' => true,
            ]
        );

        Role::updateOrCreate(
            ['scope' => 'organization', 'key' => 'org_manager'],
            [
                'name' => 'Org Manager',
                'description' => 'Organization manager',
                'is_system' => true,
                'is_active' => true,
            ]
        );

        Role::updateOrCreate(
            ['scope' => 'organization', 'key' => 'media_buyer'],
            [
                'name' => 'Media Buyer',
                'description' => 'Organization media buyer',
                'is_system' => true,
                'is_active' => true,
            ]
        );
    }
}
