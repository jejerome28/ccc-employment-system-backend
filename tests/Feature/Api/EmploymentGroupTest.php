<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DemoEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmploymentGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'employee_code' => 'EMP-9',
            'first_name' => 'Zed',
            'last_name' => 'Zulu',
            'status' => 'active',
        ], $extra);
    }

    public function test_store_and_update_accept_group_and_reject_unknown(): void
    {
        $id = $this->postJson('/api/employees', $this->payload(['employment_group' => 'cos_teaching']))
            ->assertCreated()
            ->assertJsonPath('data.employee.employment_group', 'cos_teaching')
            ->json('data.employee.id');

        $this->putJson("/api/employees/{$id}", $this->payload(['employment_group' => 'foo']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment_group', 'data.errors');

        $this->putJson("/api/employees/{$id}", $this->payload(['employment_group' => null]))
            ->assertOk()
            ->assertJsonPath('data.employee.employment_group', null);
    }

    public function test_index_filters_by_group_and_none(): void
    {
        Employee::factory()->create(['employee_code' => 'A', 'employment_group' => 'cos_teaching']);
        Employee::factory()->create(['employee_code' => 'B', 'employment_group' => 'permanent']);
        Employee::factory()->create(['employee_code' => 'C', 'employment_group' => null]);

        $codes = fn (string $g) => collect($this->getJson("/api/employees?group={$g}")->assertOk()->json('data.items'))
            ->pluck('employee_code')->all();

        $this->assertSame(['A'], $codes('cos_teaching'));
        $this->assertSame(['C'], $codes('none'));
        $this->getJson('/api/employees?group=foo')->assertStatus(422);
    }

    public function test_demo_seeder_assigns_groups(): void
    {
        $this->seed(DemoEmployeeSeeder::class);

        $group = fn (string $code) => Employee::where('employee_code', $code)->value('employment_group')->value;

        $this->assertSame('permanent', $group('EMP-1002'));
        $this->assertSame('cos_teaching', $group('EMP-1007'));
        $this->assertSame('cos_non_teaching', $group('EMP-1004'));
        $this->assertSame('permanent_non_teaching', $group('EMP-1001'));
    }
}
