<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Platform Super Admin (no tenant). Credentials overridable via env.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'superadmin@hrms.test');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Platform Super Admin',
                'company_id' => null,
                'is_super_admin' => true,
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
            ],
        );

        $user->syncRoles(['Super Admin']);
    }
}
