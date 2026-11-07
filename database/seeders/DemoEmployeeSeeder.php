<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class DemoEmployeeSeeder extends Seeder
{
    public const EMPLOYEES = [
        ['1001', 'Ana', 'Cruz', 'Registrar Staff', 'Registrar'],
        ['1002', 'Ben', 'Santos', 'Instructor I', 'College of Computer Studies'],
        ['1003', 'Carla', 'Reyes', 'Accountant', 'Accounting'],
        ['1004', 'Dan', 'Mendoza', 'Utility Worker', 'General Services'],
        ['1005', 'Ella', 'Garcia', 'HR Assistant', 'Human Resources'],
        ['1006', 'Fe', 'Torres', 'Librarian', 'Library'],
        ['1007', 'Gino', 'Ramos', 'Instructor II', 'College of Education'],
        ['1008', 'Hana', 'Lim', 'IT Support', 'MIS Office'],
        ['1009', 'Ivan', 'Dela Cruz', 'Security Guard', 'General Services'],
        ['1010', 'Joy', 'Bautista', 'Cashier', 'Accounting'],
    ];

    public function run(): void
    {
        foreach (self::EMPLOYEES as $i => [$personId, $first, $last, $position, $department]) {
            Employee::updateOrCreate(
                ['employee_code' => "EMP-{$personId}"],
                [
                    'biometric_id' => $personId,
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => strtolower("{$first}.".str_replace(' ', '', $last).'@example.com'),
                    'phone' => '09'.str_pad((string) (170000000 + $i), 9, '0', STR_PAD_LEFT),
                    'position' => $position,
                    'department' => $department,
                    'hire_date' => '2025-06-01',
                    'status' => 'active',
                ]
            );
        }
    }
}
