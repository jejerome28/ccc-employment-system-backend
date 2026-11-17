<?php

namespace App\Services;

use App\Enums\EmploymentGroup;
use App\Models\Employee;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

class EmployeeRosterImporter
{
    public const HEADER = [
        'Person ID (biometric) *', 'Employee code *', 'First name *', 'Last name *', 'Hire date * (YYYY-MM-DD)',
        'Employment group *', 'Position', 'Department', 'Email', 'Phone',
    ];

    private const LABELS = [
        'biometric_id' => 'Person ID', 'employee_code' => 'Employee code', 'first_name' => 'First name',
        'last_name' => 'Last name', 'hire_date' => 'Hire date', 'employment_group' => 'Employment group',
        'position' => 'Position', 'department' => 'Department', 'email' => 'Email', 'phone' => 'Phone',
    ];

    /**
     * @return array{rows: list<array<string, mixed>>, errors: list<array{line: int, message: string}>}
     */
    public function parse(string $path): array
    {
        $lines = $this->rosterSheet($path);
        if (is_string($lines)) {
            return ['rows' => [], 'errors' => [['line' => 1, 'message' => $lines]]];
        }
        if (($lines[1] ?? []) !== self::HEADER) {
            return ['rows' => [], 'errors' => [['line' => 1, 'message' => 'The columns do not match the template. Download the template and copy your rows into it.']]];
        }

        $groups = [];
        foreach (EmploymentGroup::cases() as $g) {
            $groups[strtolower($g->shortLabel())] = $g->value;
        }

        $rows = [];
        $errors = [];
        $seen = [];

        foreach ($lines as $line => $cells) {
            if ($line === 1 || implode('', $cells) === '') {
                continue;
            }

            $row = array_combine(array_keys(self::LABELS), array_map(fn ($v) => $v === '' ? null : $v, $cells));
            $rawGroup = $row['employment_group'];
            $row['employment_group'] = $rawGroup === null ? null : ($groups[strtolower($rawGroup)] ?? $rawGroup);

            $rowErrors = Validator::make($row, [
                'biometric_id' => ['required', 'string', 'max:30'],
                'employee_code' => ['required', 'string', 'max:30'],
                'first_name' => ['required', 'string', 'max:60'],
                'last_name' => ['required', 'string', 'max:60'],
                'hire_date' => ['required', 'date_format:Y-m-d'],
                'employment_group' => ['required', Rule::enum(EmploymentGroup::class)],
                'position' => ['nullable', 'string', 'max:80'],
                'department' => ['nullable', 'string', 'max:80'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
            ], [
                'employment_group.enum' => 'Employment group "'.$rawGroup.'" is not in the list.',
                'hire_date.date_format' => 'Hire date "'.$row['hire_date'].'" is not YYYY-MM-DD.',
            ], self::LABELS)->errors()->all();

            foreach (['biometric_id', 'employee_code', 'email'] as $key) {
                if ($row[$key] === null) {
                    continue;
                }
                $value = strtolower($row[$key]);
                if (isset($seen[$key][$value])) {
                    $rowErrors[] = self::LABELS[$key].' "'.$row[$key].'" is also on line '.$seen[$key][$value].'.';
                } else {
                    $seen[$key][$value] = $line;
                }
            }

            foreach ($rowErrors as $message) {
                $errors[] = ['line' => $line, 'message' => $message];
            }
            if (! $rowErrors) {
                $rows[] = ['line' => $line] + $row;
            }
        }

        $errors = $errors ?: $this->conflicts($rows);

        return ['rows' => $errors ? [] : $rows, 'errors' => $errors];
    }

    /**
     * @param  list<array<string, mixed>>  $rows  from parse()
     * @return array{created: int, updated: int, restored: int}
     */
    public function save(array $rows): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'restored' => 0];

        DB::transaction(function () use ($rows, &$counts) {
            foreach ($rows as $row) {
                // Blank optional cells never overwrite saved values.
                $values = array_filter(array_diff_key($row, ['line' => true]), fn ($v) => $v !== null) + ['status' => 'active'];
                $employee = Employee::withTrashed()->firstWhere('biometric_id', $row['biometric_id']);

                if (! $employee) {
                    Employee::create($values);
                    $counts['created']++;

                    continue;
                }

                if ($employee->trashed()) {
                    $employee->restore();
                    $counts['restored']++;
                } else {
                    $counts['updated']++;
                }
                $employee->fill($values)->save();
            }
        });

        return $counts;
    }

    /**
     * Code / email already used by a different employee (archived included) than the one this Person ID matches.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{line: int, message: string}>
     */
    private function conflicts(array $rows): array
    {
        $existing = Employee::withTrashed()
            ->whereIn('biometric_id', array_column($rows, 'biometric_id'))
            ->orWhereIn('employee_code', array_column($rows, 'employee_code'))
            ->orWhereIn('email', array_filter(array_column($rows, 'email')))
            ->get();
        $errors = [];

        foreach ($rows as $row) {
            $self = $existing->first(fn (Employee $e) => $e->biometric_id === $row['biometric_id']);
            foreach (['employee_code', 'email'] as $key) {
                $owner = $row[$key] === null ? null : $existing->first(
                    fn (Employee $e) => $e->id !== $self?->id && $e->{$key} !== null && strcasecmp($e->{$key}, $row[$key]) === 0,
                );
                if ($owner) {
                    $errors[] = ['line' => $row['line'], 'message' => self::LABELS[$key].' "'.$row[$key].'" already belongs to '.$owner->full_name.'.'];
                }
            }
        }

        return $errors;
    }

    /**
     * Roster sheet cells as trimmed strings (dates Y-m-d), keyed by Excel row number; or an error message.
     *
     * @return array<int, list<string>>|string
     */
    private function rosterSheet(string $path): array|string
    {
        // Empty rows preserved so keys stay equal to Excel row numbers.
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));

        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                if (strcasecmp(trim($sheet->getName()), 'Roster') !== 0) {
                    continue;
                }
                $lines = [];
                foreach ($sheet->getRowIterator() as $n => $row) {
                    $cells = array_map(
                        fn ($v) => $v instanceof DateTimeInterface ? $v->format('Y-m-d') : trim((string) $v),
                        array_slice($row->toArray(), 0, 10),
                    );
                    // Blank rows are skipped (a stray far-away cell would otherwise store ~1M rows).
                    if (implode('', $cells) !== '') {
                        $lines[$n] = array_pad($cells, 10, '');
                    }
                }

                return $lines;
            }

            return 'Sheet "Roster" not found. Use the template.';
        } catch (Throwable) {
            return 'Could not read the file as Excel .xlsx.';
        } finally {
            $reader->close();
        }
    }
}
