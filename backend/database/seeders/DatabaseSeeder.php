<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database idempotently.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@carrier.isp'],
            [
                'name' => 'Carrier Super Administrator',
                'password' => Hash::make('Carrier@Admin2026!'),
                'email_verified_at' => now(),
            ]
        );
    }
}
