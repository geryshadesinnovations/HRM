<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ModuleSeeder::class,
            PlanSeeder::class,
            RolePermissionSeeder::class,
            SuperAdminSeeder::class,
        ]);

        // Demo tenant with per-role logins — skipped during automated tests so
        // it never interferes with assertions.
        if (! app()->environment('testing')) {
            $this->call(DemoSeeder::class);
        }
    }
}
