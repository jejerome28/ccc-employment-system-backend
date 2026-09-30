<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceClockMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_manila_times_become_utc_and_roll_back(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

        $employeeId = DB::table('employees')->insertGetId([
            'employee_code' => 'EMP-1', 'first_name' => 'James', 'last_name' => 'Villaflor', 'status' => 'active',
        ]);
        DB::table('attendances')->insert([
            ['employee_id' => $employeeId, 'work_date' => '2026-06-22', 'time_in' => '07:04:14', 'time_out' => '18:37:44'],
            ['employee_id' => $employeeId, 'work_date' => '2026-06-23', 'time_in' => '22:00:00', 'time_out' => '06:00:00'],
            ['employee_id' => $employeeId, 'work_date' => '2026-06-24', 'time_in' => null, 'time_out' => null],
        ]);

        $this->artisan('migrate')->assertSuccessful();

        $rows = DB::table('attendances')->orderBy('work_date')->get();
        $this->assertSame('2026-06-21 23:04:14', $rows[0]->clock_in_at);
        $this->assertSame('2026-06-22 10:37:44', $rows[0]->clock_out_at);
        $this->assertSame('2026-06-23 14:00:00', $rows[1]->clock_in_at);
        $this->assertSame('2026-06-23 22:00:00', $rows[1]->clock_out_at);
        $this->assertNull($rows[2]->clock_in_at);
        $this->assertObjectNotHasProperty('time_in', $rows[0]);

        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

        $rows = DB::table('attendances')->orderBy('work_date')->get();
        $this->assertSame('07:04:14', $rows[0]->time_in);
        $this->assertSame('06:00:00', $rows[1]->time_out);
        $this->assertNull($rows[2]->time_in);
        $this->assertObjectNotHasProperty('clock_in_at', $rows[0]);
    }
}
