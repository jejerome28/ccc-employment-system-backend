<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The only way into the app. There is no registration screen, so
        // accounts are created here (or with `php artisan tinker`).
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'System Administrator', 'password' => Hash::make(env('ADMIN_PASSWORD') ?? throw new \RuntimeException('Set ADMIN_PASSWORD in .env'))]
        );

        $this->call(DemoEmployeeSeeder::class);
    }
}
