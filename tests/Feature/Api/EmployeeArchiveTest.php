<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Services\BiometricReportImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
    }

    private function archived(array $attrs = []): Employee
    {
        $e = Employee::factory()->create($attrs);
        $e->delete();

        return $e;
    }

    public function test_attendance_store_rejects_archived_employee(): void
    {
        $e = $this->archived();

        $this->postJson('/api/attendance', ['employee_id' => $e->id, 'work_date' => '2026-10-01'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('employee_id', 'data.errors');
    }

    public function test_destroy_archives_and_keeps_attendance(): void
    {
        $e = Employee::factory()->create();
        Attendance::factory()->for($e)->create();

        $this->deleteJson("/api/employees/{$e->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Employee archived.');

        $this->assertSoftDeleted($e);
        $this->assertSame(1, Attendance::count());
    }

    public function test_archived_employee_is_404_and_hidden_from_list(): void
    {
        $e = $this->archived();

        $this->getJson("/api/employees/{$e->id}?month=2026-11")->assertNotFound();
        $this->assertNotContains($e->id, collect($this->getJson('/api/employees')->json('data.items'))->pluck('id')->all());
    }

    public function test_archived_filter_lists_only_archived(): void
    {
        Employee::factory()->create();
        $e = $this->archived();

        $res = $this->getJson('/api/employees?archived=1')->assertOk();

        $this->assertSame([$e->id], collect($res->json('data.items'))->pluck('id')->all());
        $this->assertNotNull($res->json('data.items.0.archived_at'));
    }

    public function test_restore_brings_employee_back(): void
    {
        $e = $this->archived();

        $this->postJson("/api/employees/{$e->id}/restore")
            ->assertOk()
            ->assertJsonPath('message', 'Employee restored.');

        $this->assertNull($e->fresh()->deleted_at);
    }

    public function test_attendance_index_still_lists_archived_employees_records(): void
    {
        $e = $this->archived();
        Attendance::factory()->for($e)->create(['work_date' => '2026-11-03']);

        $this->getJson('/api/attendance?date=2026-11-03')
            ->assertOk()
            ->assertJsonPath('data.attendances.0.employee.id', $e->id);
    }

    public function test_summary_keeps_archived_with_attendance_only(): void
    {
        $a = $this->archived(['hire_date' => '2026-01-01']);
        $b = $this->archived(['hire_date' => '2026-01-01']);
        $c = Employee::factory()->create(['hire_date' => '2026-01-01']);
        Attendance::factory()->for($a)->create([
            'work_date' => '2026-11-09',
            'clock_in_at' => '2026-11-08 23:00:00',
            'clock_out_at' => '2026-11-09 09:00:00',
        ]);

        $ids = collect($this->getJson('/api/attendance/summary?from=2026-11-09&to=2026-11-15')->assertOk()->json('data.rows'))->pluck('employee_id')->all();

        $this->assertContains($a->id, $ids);
        $this->assertContains($c->id, $ids);
        $this->assertNotContains($b->id, $ids);
    }

    public function test_import_skips_archived_employee(): void
    {
        $e = $this->archived(['biometric_id' => '263']);
        $csv = '751,263,Villaflor James Matthew H.,CCC,-,Male,2026-06-22,Mon.,Default Timetable(00:00-23:59:00),07:04:14,18:37:44,480,0,694,0,0,0,0,P,-';
        $path = tempnam(sys_get_temp_dir(), 'bio');
        file_put_contents($path, $csv);
        $importer = app(BiometricReportImporter::class);

        $result = $importer->save($importer->parse($path)['rows']);

        $this->assertSame(0, $result['created']);
        $this->assertCount(1, $result['unknown']);
        $this->assertSoftDeleted($e);
    }
}
