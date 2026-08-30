<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class RoleUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = config('leadgen.seed_accounts');

        foreach ($accounts as $account) {
            User::updateOrCreate(['email' => $account['email']], [...$account, 'password' => config('leadgen.seed_password'), 'status' => 'active', 'email_verified_at' => now()]);
        }
    }
}
