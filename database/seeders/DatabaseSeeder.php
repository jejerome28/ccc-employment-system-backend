<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
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

        $employees = [
            ['EMP-001', 'Maria', 'Santos', 'maria.santos@example.com', 'HR Officer', 'Human Resources'],
            ['EMP-002', 'Jose', 'Dela Cruz', 'jose.delacruz@example.com', 'Warehouse Lead', 'Operations'],
            ['EMP-003', 'Angelica', 'Reyes', 'angelica.reyes@example.com', 'Accountant', 'Finance'],
            ['EMP-004', 'Rafael', 'Mendoza', 'rafael.mendoza@example.com', 'Field Technician', 'Operations'],
            ['EMP-005', 'Camille', 'Villanueva', 'camille.v@example.com', 'Sales Associate', 'Sales'],
        ];

        foreach ($employees as $i => [$code, $first, $last, $email, $position, $department]) {
            $employee = Employee::updateOrCreate(
                ['employee_code' => $code],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $email,
                    'phone' => '09' . str_pad((string) (170000000 + $i), 9, '0', STR_PAD_LEFT),
                    'position' => $position,
                    'department' => $department,
                    'hire_date' => now()->subMonths(6 + $i)->toDateString(),
                    'status' => 'active',
                ]
            );

            // Two weeks of sample attendance so reports have something to show.
            for ($d = 1; $d <= 14; $d++) {
                $date = now()->subDays($d);

                if ($date->isWeekend()) {
                    continue;
                }

                Attendance::updateOrCreate(
                    ['employee_id' => $employee->id, 'work_date' => $date->toDateString()],
                    [
                        'time_in' => sprintf('%02d:%02d:00', 8, rand(0, 25)),
                        'time_out' => sprintf('%02d:%02d:00', 17, rand(0, 40)),
                    ]
                );
            }
        }
    }
}
