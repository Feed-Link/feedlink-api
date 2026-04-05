<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Donor permissions
            'create listing', 'view listing', 'update listing', 'delete listing',
            'confirm claim', 'reject claim',
            'accept request', 'cancel request acceptance',
            'browse requests',

            // Recipient permissions
            'view listings',
            'create claim', 'cancel claim',
            'view claim',
            'create request', 'update request', 'delete request',

            // Admin permissions (all)
            'manage all',
        ];

        foreach ($permissions as $name) {
            \App\Models\Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'api',
            ]);
        }

        // Create roles with permissions
        $donor = Role::firstOrCreate(['name' => 'donor', 'guard_name' => 'api']);
        $donor->givePermissionTo([
            'create listing', 'view listing', 'update listing', 'delete listing',
            'confirm claim', 'reject claim',
            'accept request', 'cancel request acceptance',
            'browse requests',
        ]);

        $recipient = Role::firstOrCreate(['name' => 'recipient', 'guard_name' => 'api']);
        $recipient->givePermissionTo([
            'view listings',
            'create claim', 'cancel claim',
            'view claim',
            'create request', 'update request', 'delete request',
        ]);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $admin->givePermissionTo('manage all');
    }
}
