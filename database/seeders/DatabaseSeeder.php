<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /** Creates the three roles and the first Super Admin. Safe to run more than once. */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(User::ROLE_LABELS) as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->call(DepartmentSeeder::class);

        $email = env('ADMIN_EMAIL', 'admin@mdbiocoals.com');
        $admin = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Super Admin', 'password' => env('ADMIN_PASSWORD', 'ChangeMe@123'), 'is_active' => true],
        );
        $admin->syncRoles([User::SUPER_ADMIN]);

        if ($admin->wasRecentlyCreated) {
            $this->command?->info("Super Admin created: {$email} / ".env('ADMIN_PASSWORD', 'ChangeMe@123').' (change this after first login)');
        }
    }
}
