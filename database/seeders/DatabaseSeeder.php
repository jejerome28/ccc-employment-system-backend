<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // There is no registration screen, so accounts are created here (or with
        // `php artisan tinker`). All three share ADMIN_PASSWORD.
        $password = Hash::make(env('ADMIN_PASSWORD') ?? throw new \RuntimeException('Set ADMIN_PASSWORD in .env'));

        foreach ([
            'admin@example.com' => ['System Administrator', Role::Admin],
            'hr@example.com' => ['HR Staff', Role::Hr],
            'viewer@example.com' => ['Viewer', Role::Viewer],
        ] as $email => [$name, $role]) {
            User::updateOrCreate(['email' => $email], ['name' => $name, 'role' => $role, 'password' => $password]);
        }

        $this->call(DemoEmployeeSeeder::class);
    }
}
