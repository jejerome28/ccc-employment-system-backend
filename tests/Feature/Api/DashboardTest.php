<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_stats_count_each_clock_state(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $clockedIn = Employee::factory()->create(['last_name' => 'Abad']);
        $done = Employee::factory()->create(['last_name' => 'Bautista']);
        Employee::factory()->create(['last_name' => 'Cruz']);
        Employee::factory()->inactive()->create();
        Attendance::factory()->for($clockedIn)->create(['time_in' => '08:00', 'time_out' => null]);
        Attendance::factory()->for($done)->create(['time_in' => '08:00', 'time_out' => '12:30']);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats', [
                'employees' => 4,
                'active' => 3,
                'clocked_in' => 1,
                'completed' => 1,
                'not_in' => 1,
            ])
            ->assertJsonPath('data.hours_today', 4.5)
            ->assertJsonCount(3, 'data.employees')
            ->assertJsonPath('data.employees.0.last_name', 'Abad')
            ->assertJsonPath('data.employees.0.today_attendance.time_in', '08:00:00')
            ->assertJsonPath('data.employees.2.today_attendance', null);
    }
}
