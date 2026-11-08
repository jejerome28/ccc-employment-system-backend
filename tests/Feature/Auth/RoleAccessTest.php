<?php

namespace Tests\Feature\Auth;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
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

    public function test_viewer_can_read(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());

        $this->getJson('/api/employees')->assertOk();
        $this->getJson('/api/attendance/summary?from=2026-11-01&to=2026-11-15')->assertOk();
    }

    public static function writeRoutes(): array
    {
        return [
            'employee store' => ['POST', '/api/employees'],
            'employee update' => ['PUT', '/api/employees/{e}'],
            'time-in' => ['POST', '/api/employees/{e}/time-in'],
            'attendance store' => ['POST', '/api/attendance'],
            'attendance destroy' => ['DELETE', '/api/attendance/{a}'],
        ];
    }

    #[DataProvider('writeRoutes')]
    public function test_viewer_cannot_write(string $method, string $uri): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());
        $e = Employee::factory()->create();
        $a = Attendance::factory()->for($e)->create();

        $this->json($method, str_replace(['{e}', '{a}'], [$e->id, $a->id], $uri), $this->valid)
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You do not have permission to do this.');
    }

    public function test_hr_can_write_but_not_admin_actions(): void
    {
        Sanctum::actingAs(User::factory()->hr()->create());
        $e = Employee::factory()->create(['employee_code' => 'EMP-100', 'email' => 'maria@example.com']);

        $this->putJson("/api/employees/{$e->id}", [...$this->valid, 'position' => 'HR Head'])->assertOk();
        $this->deleteJson("/api/employees/{$e->id}")->assertForbidden();
        $this->assertNotSoftDeleted($e);
        $this->postJson('/api/attendance/import')->assertForbidden();
    }

    public function test_admin_can_archive(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $e = Employee::factory()->create();

        $this->deleteJson("/api/employees/{$e->id}")->assertOk();
    }

    public function test_web_viewer_cannot_delete_employee(): void
    {
        $e = Employee::factory()->create();

        $this->actingAs(User::factory()->viewer()->create())
            ->delete("/employees/{$e->id}")
            ->assertForbidden();
        $this->assertNotSoftDeleted($e);
    }
}
