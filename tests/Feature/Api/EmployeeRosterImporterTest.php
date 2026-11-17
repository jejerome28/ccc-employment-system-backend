<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Services\EmployeeRosterImporter;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RosterXlsx;
use Tests\TestCase;

class EmployeeRosterImporterTest extends TestCase
{
    use RefreshDatabase;

    private const ROW = ['1001', 'EMP-1001', 'Ana', 'Cruz', '2020-06-01', 'Permanent Non-Teaching', 'Registrar Staff', 'Registrar', 'ana@example.com', '0917'];

    private function parse(string $path): array
    {
        return app(EmployeeRosterImporter::class)->parse($path);
    }

    public function test_parses_rows_maps_group_and_date_cell(): void
    {
        $first = self::ROW;
        $first[4] = new DateTimeImmutable('2020-06-01');

        $result = $this->parse(RosterXlsx::write([
            $first,
            null,
            ['1002', 'EMP-1002', 'Ben', 'Santos', '2019-08-12', 'cos teaching'],
        ]));

        $this->assertSame([], $result['errors']);
        $this->assertSame([
            'line' => 2, 'biometric_id' => '1001', 'employee_code' => 'EMP-1001', 'first_name' => 'Ana', 'last_name' => 'Cruz',
            'hire_date' => '2020-06-01', 'employment_group' => 'permanent_non_teaching', 'position' => 'Registrar Staff',
            'department' => 'Registrar', 'email' => 'ana@example.com', 'phone' => '0917',
        ], $result['rows'][0]);
        $this->assertSame(4, $result['rows'][1]['line']);
        $this->assertSame('cos_teaching', $result['rows'][1]['employment_group']);
        $this->assertNull($result['rows'][1]['position']);
        $this->assertNull($result['rows'][1]['email']);
    }

    public function test_numeric_person_id_becomes_string(): void
    {
        $row = self::ROW;
        $row[0] = 1001;

        $this->assertSame('1001', $this->parse(RosterXlsx::write([$row]))['rows'][0]['biometric_id']);
    }

    public function test_text_person_id_keeps_leading_zeros(): void
    {
        $row = self::ROW;
        $row[0] = '01001';

        $this->assertSame('01001', $this->parse(RosterXlsx::write([$row]))['rows'][0]['biometric_id']);
    }

    public function test_row_errors_have_excel_line_numbers_and_no_rows_are_returned(): void
    {
        $result = $this->parse(RosterXlsx::write([
            ['1001', 'EMP-1', '', 'Cruz', '2020-06-01', 'Permanent Teaching'],
            ['1002', 'EMP-2', 'Ben', 'Santos', '2020-06-01', 'Contractual'],
            ['1003', 'EMP-3', 'Carla', 'Reyes', '2020-02-31', 'COS Teaching'],
            ['1001', 'EMP-4', 'Dan', 'Mendoza', '2020-06-01', 'COS Teaching'],
        ]));

        $this->assertSame([], $result['rows']);
        $this->assertSame([
            ['line' => 2, 'message' => 'The First name field is required.'],
            ['line' => 3, 'message' => 'Employment group "Contractual" is not in the list.'],
            ['line' => 4, 'message' => 'Hire date "2020-02-31" is not YYYY-MM-DD.'],
            ['line' => 5, 'message' => 'Person ID "1001" is also on line 2.'],
        ], $result['errors']);
    }

    public function test_header_must_match_template(): void
    {
        $header = EmployeeRosterImporter::HEADER;
        [$header[2], $header[3]] = [$header[3], $header[2]];

        $this->assertSame(
            [['line' => 1, 'message' => 'The columns do not match the template. Download the template and copy your rows into it.']],
            $this->parse(RosterXlsx::write([self::ROW], header: $header))['errors'],
        );
    }

    public function test_roster_sheet_is_required(): void
    {
        $this->assertSame(
            [['line' => 1, 'message' => 'Sheet "Roster" not found. Use the template.']],
            $this->parse(RosterXlsx::write([self::ROW], 'Sheet1'))['errors'],
        );
    }

    public function test_unreadable_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'roster');
        file_put_contents($path, "Seq,Person ID\n1,1001\n");

        $this->assertSame(
            [['line' => 1, 'message' => 'Could not read the file as Excel .xlsx.']],
            $this->parse($path)['errors'],
        );
    }

    public function test_code_or_email_of_another_employee_is_an_error(): void
    {
        Employee::factory()->create(['biometric_id' => '9999', 'employee_code' => 'EMP-1001', 'first_name' => 'Old', 'last_name' => 'Owner']);
        Employee::factory()->create(['biometric_id' => '8888', 'email' => 'ana@example.com', 'first_name' => 'Gone', 'last_name' => 'Person'])->delete();

        $this->assertSame([
            ['line' => 2, 'message' => 'Employee code "EMP-1001" already belongs to Old Owner.'],
            ['line' => 2, 'message' => 'Email "ana@example.com" already belongs to Gone Person.'],
        ], $this->parse(RosterXlsx::write([self::ROW]))['errors']);
    }

    public function test_own_code_and_email_are_not_conflicts(): void
    {
        Employee::factory()->create(['biometric_id' => '1001', 'employee_code' => 'EMP-1001', 'email' => 'ana@example.com']);

        $this->assertSame([], $this->parse(RosterXlsx::write([self::ROW]))['errors']);
    }

    public function test_person_id_match_is_strict(): void
    {
        $other = Employee::factory()->create(['biometric_id' => '01001', 'employee_code' => 'EMP-1001']);

        $this->assertSame(
            [['line' => 2, 'message' => 'Employee code "EMP-1001" already belongs to '.$other->full_name.'.']],
            $this->parse(RosterXlsx::write([self::ROW]))['errors'],
        );
    }
}
