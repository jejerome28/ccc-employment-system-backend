<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    private array $valid = [
        'employee_code' => 'EMP-100',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria@example.com',
        'phone' => null,
        'position' => 'HR Officer',
        'department' => 'Human Resources',
        'hire_date' => '2026-01-15',
        'status' => 'active',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_index_paginates_12_ordered_by_last_name(): void
    {
        Employee::factory()->count(13)->create();
        Employee::factory()->create(['last_name' => 'Aaron']);

        $this->getJson('/api/employees')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(12, 'data.items')
            ->assertJsonPath('data.items.0.last_name', 'Aaron')
            ->assertJsonPath('data.meta.total', 14)
            ->assertJsonPath('data.meta.per_page', 12)
            ->assertJsonPath('data.meta.last_page', 2);
    }

    public function test_index_filters_by_q_and_status(): void
    {
        Employee::factory()->create(['first_name' => 'Maria', 'status' => 'active']);
        Employee::factory()->create(['first_name' => 'Maria', 'status' => 'inactive']);
        Employee::factory()->create(['first_name' => 'Jose']);

        $this->getJson('/api/employees?q=Maria&status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.first_name', 'Maria')
            ->assertJsonPath('data.items.0.status', 'active');
    }

    public function test_index_includes_today_attendance(): void
    {
        $e = Employee::factory()->create();
        Attendance::factory()->for($e)->create(['time_in' => '08:05', 'time_out' => null]);

        $this->getJson('/api/employees')
            ->assertJsonPath('data.items.0.today_attendance.time_in', '08:05:00')
            ->assertJsonPath('data.items.0.today_attendance.time_out', null);
    }

    public function test_index_rejects_bad_status(): void
    {
        $this->getJson('/api/employees?status=fired')
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.status.0', fn ($m) => is_string($m));
    }

    public function test_store_creates_employee(): void
    {
        $this->postJson('/api/employees', $this->valid)
            ->assertCreated()
            ->assertJsonPath('message', 'Employee created.')
            ->assertJsonPath('data.employee.full_name', 'Maria Santos')
            ->assertJsonPath('data.employee.hire_date', '2026-01-15');

        $this->assertDatabaseHas('employees', ['employee_code' => 'EMP-100']);
    }

    public function test_store_rejects_duplicate_code_and_missing_names(): void
    {
        Employee::factory()->create(['employee_code' => 'EMP-100']);

        $this->postJson('/api/employees', [...$this->valid, 'first_name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_code', 'first_name'], 'data.errors');
    }

    public function test_store_rejects_unknown_field(): void
    {
        $this->postJson('/api/employees', [...$this->valid, 'remember' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['remember'], 'data.errors');
    }

    public function test_show_returns_month_attendance_and_totals(): void
    {
        $e = Employee::factory()->create();
        Attendance::factory()->for($e)->create(['work_date' => '2026-08-03', 'time_in' => '08:00', 'time_out' => '17:00']);
        Attendance::factory()->for($e)->create(['work_date' => '2026-08-04', 'time_in' => '08:00', 'time_out' => '12:00']);
        Attendance::factory()->for($e)->create(['work_date' => '2026-09-01']);

        $this->getJson("/api/employees/{$e->id}?month=2026-08")
            ->assertOk()
            ->assertJsonPath('data.employee.id', $e->id)
            ->assertJsonCount(2, 'data.attendances')
            ->assertJsonPath('data.attendances.0.work_date', '2026-08-04')
            ->assertJsonPath('data.total_minutes', 780)
            ->assertJsonPath('data.days_present', 2);
    }

    public function test_show_rejects_bad_month_and_missing_employee(): void
    {
        $e = Employee::factory()->create();

        $this->getJson("/api/employees/{$e->id}?month=August")->assertUnprocessable();
        $this->getJson('/api/employees/999')->assertNotFound()->assertJsonPath('success', false);
    }

    public function test_update_ignores_own_unique_values(): void
    {
        $e = Employee::factory()->create(['employee_code' => 'EMP-100', 'email' => 'maria@example.com']);

        $this->putJson("/api/employees/{$e->id}", [...$this->valid, 'position' => 'HR Head'])
            ->assertOk()
            ->assertJsonPath('message', 'Employee updated.')
            ->assertJsonPath('data.employee.position', 'HR Head');
    }

    public function test_destroy_deletes_employee_and_attendance(): void
    {
        $e = Employee::factory()->create();
        Attendance::factory()->for($e)->create();

        $this->deleteJson("/api/employees/{$e->id}")
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Employee deleted.', 'data' => null]);

        $this->assertDatabaseCount('attendances', 0);
        $this->getJson("/api/employees/{$e->id}")->assertNotFound();
    }
}
