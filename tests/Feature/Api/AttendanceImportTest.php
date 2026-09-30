<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceImportTest extends TestCase
{
    use RefreshDatabase;

    private function upload(string $csv, string $name = 'report.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $csv);
    }

    private function fixture(): string
    {
        return file_get_contents(base_path('tests/Fixtures/biometric-report.csv'));
    }

    public function test_guest_gets_401(): void
    {
        $this->postJson('/api/attendance/import')->assertUnauthorized();
    }

    public function test_imports_then_reimport_updates(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Employee::factory()->create(['biometric_id' => '263']);

        $this->postJson('/api/attendance/import', ['file' => $this->upload($this->fixture())])
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Imported 11 rows.',
                'data' => ['created' => 11, 'updated' => 0, 'unknown' => []],
            ]);

        $this->postJson('/api/attendance/import', ['file' => $this->upload($this->fixture())])
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 11);

        $this->assertDatabaseCount('attendances', 11);
    }

    public function test_unknown_person_ids_are_listed(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/attendance/import', ['file' => $this->upload($this->fixture())])
            ->assertOk()
            ->assertJsonPath('message', 'Imported 0 rows.')
            ->assertJsonCount(11, 'data.unknown')
            ->assertJsonPath('data.unknown.0', ['line' => 2, 'person_id' => '263', 'name' => 'Villaflor James Matthew H.']);
    }

    public function test_row_errors_write_nothing(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Employee::factory()->create(['biometric_id' => '263']);
        $csv = str_replace('2026-06-24', '2026-06-31', $this->fixture());

        $this->postJson('/api/attendance/import', ['file' => $this->upload($csv)])
            ->assertUnprocessable()
            ->assertExactJson([
                'success' => false,
                'message' => 'The file has errors. Nothing was imported.',
                'data' => ['errors' => [['line' => 9, 'message' => 'Date "2026-06-31" is not YYYY-MM-DD.']]],
            ]);

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_file_without_rows_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/attendance/import', ['file' => $this->upload("Date/Time: 2026-06-30 08:16:49\n")])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No attendance rows found.');
    }

    public function test_upload_rules(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/attendance/import')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file'], 'data.errors');

        $this->postJson('/api/attendance/import', ['file' => UploadedFile::fake()->image('photo.png')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file'], 'data.errors');

        $this->postJson('/api/attendance/import', ['file' => UploadedFile::fake()->create('big.csv', 5121, 'text/csv')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file'], 'data.errors');
    }
}
