<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@haciendamonte-rico.com')],
            [
                'name' => env('ADMIN_NAME', 'Administrador Hacienda MonteRico'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Admin1234!')),
                'role' => 'admin',
                'status' => 'active',
                'must_change_password' => false,
                'email_verified_at' => now(),
            ]
        );
    }
}
