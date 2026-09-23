<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the super admin account.
     */
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@evoriq.test'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('123456'),
            ],
        );

        if (! $superAdmin->hasVerifiedEmail()) {
            $superAdmin->markEmailAsVerified();
        }

        if (! $superAdmin->hasRole(UserRole::SUPER_ADMIN->value)) {
            $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
        }
    }
}
