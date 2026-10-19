<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Known demo passwords are for local development only.
        if (app()->isProduction()) {
            $this->command?->warn('Skipping demo users in production. Create the first admin manually.');

            return;
        }

        User::updateOrCreate(['email' => 'admin@stylepos.test'], [
            'name' => 'Store Admin', 'password' => 'password', 'role' => Role::Admin, 'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'cashier@stylepos.test'], [
            'name' => 'Demo Cashier', 'password' => 'password', 'role' => Role::Cashier, 'is_active' => true,
        ]);
    }
}
