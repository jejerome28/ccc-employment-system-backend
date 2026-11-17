<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Support\RosterXlsx;
use Tests\TestCase;

class EmployeeImportTest extends TestCase
{
    use RefreshDatabase;

    private function upload(array $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('roster.xlsx', file_get_contents(RosterXlsx::write($rows)));
    }

    public function test_guest_401_viewer_403(): void
    {
        $this->postJson('/api/employees/import')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->viewer()->create());
        $this->postJson('/api/employees/import')->assertForbidden();
    }

    public function test_creates_updates_and_restores(): void
    {
        Sanctum::actingAs(User::factory()->hr()->create());
        $kept = Employee::factory()->inactive()->create(['biometric_id' => '1002', 'first_name' => 'Old', 'position' => 'Keep me']);
        $archived = Employee::factory()->create(['biometric_id' => '1003']);
        $archived->delete();

        $this->postJson('/api/employees/import', ['file' => $this->upload([
            ['1001', 'EMP-1001', 'Ana', 'Cruz', '2020-06-01', 'Permanent Non-Teaching', 'Registrar Staff'],
            ['1002', $kept->employee_code, 'Ben', 'Santos', '2019-08-12', 'Permanent Teaching'],
            ['1003', $archived->employee_code, 'Carla', 'Reyes', '2021-01-04', 'COS Teaching'],
        ])])
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Saved 3 employees.',
                'data' => ['created' => 1, 'updated' => 1, 'restored' => 1],
            ]);

        $new = Employee::firstWhere('biometric_id', '1001');
        $this->assertSame('EMP-1001', $new->employee_code);
        $this->assertSame('2020-06-01', $new->hire_date->format('Y-m-d'));
        $this->assertSame('permanent_non_teaching', $new->employment_group->value);
        $this->assertSame('active', $new->status);

        $kept->refresh();
        $this->assertSame('Ben', $kept->first_name);
        $this->assertSame('Keep me', $kept->position);
        $this->assertSame('active', $kept->status);

        $this->assertFalse($archived->fresh()->trashed());
        $this->assertSame('cos_teaching', $archived->fresh()->employment_group->value);
    }

    public function test_reupload_is_idempotent(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $rows = [['1001', 'EMP-1001', 'Ana', 'Cruz', '2020-06-01', 'Permanent Teaching']];

        $this->postJson('/api/employees/import', ['file' => $this->upload($rows)])->assertJsonPath('data.created', 1);
        $this->postJson('/api/employees/import', ['file' => $this->upload($rows)])->assertJsonPath('data.updated', 1);

        $this->assertDatabaseCount('employees', 1);
    }

    public function test_row_errors_write_nothing(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/employees/import', ['file' => $this->upload([
            ['1001', 'EMP-1001', 'Ana', 'Cruz', '2020-06-01', 'Permanent Teaching'],
            ['1002', 'EMP-1002', 'Ben', 'Santos', '', 'Permanent Teaching'],
        ])])
            ->assertUnprocessable()
            ->assertExactJson([
                'success' => false,
                'message' => 'The file has errors. Nothing was imported.',
                'data' => ['errors' => [['line' => 3, 'message' => 'The Hire date field is required.']]],
            ]);

        $this->assertDatabaseCount('employees', 0);
    }

    public function test_empty_roster_and_upload_rules(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/employees/import', ['file' => $this->upload([])])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No employees found in the Roster sheet.');

        $this->postJson('/api/employees/import')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file'], 'data.errors');

        $this->postJson('/api/employees/import', ['file' => UploadedFile::fake()->createWithContent('roster.csv', "a,b\n")])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file'], 'data.errors');
    }
}
