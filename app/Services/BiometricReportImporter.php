<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class BiometricReportImporter
{
    private const COLUMNS = [
        'seq' => 0,
        'person_id' => 1,
        'name' => 2,
        'date' => 6,
        'timetable' => 8,
        'check_in' => 9,
        'check_out' => 10,
        'work_minutes' => 11,
        'ot_minutes' => 12,
        'attended_minutes' => 13,
        'late_minutes' => 14,
        'early_minutes' => 15,
        'absent_minutes' => 16,
        'leave_minutes' => 17,
        'status' => 18,
    ];

    private const MINUTE_LABELS = [
        'work_minutes' => 'Work',
        'ot_minutes' => 'OT',
        'attended_minutes' => 'Attended',
        'late_minutes' => 'Late',
        'early_minutes' => 'Early',
        'absent_minutes' => 'Absent',
        'leave_minutes' => 'Leave',
    ];

    /**
     * @return array{rows: list<array<string, mixed>>, errors: list<array{line: int, message: string}>}
     */
    public function parse(string $path, string $format = 'csv'): array
    {
        $tz = new DateTimeZone(config('attendance.timezone'));
        $rows = [];
        $errors = [];
        $seen = [];
        $line = 0;

        foreach ($this->lines($path, $format) as $cells) {
            $line++;
            $cells = array_map(fn ($cell) => trim($cell), $cells);

            if ($line === 1) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cells[0]);
            }

            if (! ctype_digit($cells[self::COLUMNS['seq']] ?? '')) {
                continue;
            }

            $rowErrors = [];
            $row = $this->parseRow($cells, $line, $tz, $rowErrors);

            if (! $rowErrors) {
                $key = $row['person_id'].'|'.$row['work_date'];

                if (isset($seen[$key])) {
                    $rowErrors[] = "Duplicate of line {$seen[$key]}.";
                } else {
                    $seen[$key] = $line;
                }
            }

            foreach ($rowErrors as $message) {
                $errors[] = ['line' => $line, 'message' => $message];
            }

            if (! $rowErrors) {
                $rows[] = $row;
            }
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /** @return iterable<list<string>> */
    private function lines(string $path, string $format): iterable
    {
        if ($format === 'xlsx') {
            $reader = new XlsxReader;
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    yield array_map(fn ($value) => match (true) {
                        $value instanceof DateTimeInterface => $value->format('Y-m-d') === '1899-12-30' ? $value->format('H:i:s') : $value->format('Y-m-d'),
                        $value instanceof DateInterval => $value->format('%H:%I:%S'),
                        default => (string) $value,
                    }, $row->toArray());
                }
                break;
            }
            $reader->close();

            return;
        }

        $handle = fopen($path, 'r');
        while (($cells = fgetcsv($handle, escape: '')) !== false) {
            yield array_map(
                fn ($cell) => mb_check_encoding((string) $cell, 'UTF-8') ? (string) $cell : mb_convert_encoding((string) $cell, 'UTF-8', 'Windows-1252'),
                $cells,
            );
        }
        fclose($handle);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, unknown: list<array{line: int, person_id: string, name: ?string}>}
     */
    public function save(array $rows): array
    {
        $employeeIds = Employee::whereIn('biometric_id', array_unique(array_column($rows, 'person_id')))
            ->pluck('id', 'biometric_id');
        $created = 0;
        $updated = 0;
        $unknown = [];

        DB::transaction(function () use ($rows, $employeeIds, &$created, &$updated, &$unknown) {
            foreach ($rows as $row) {
                $employeeId = $employeeIds[$row['person_id']] ?? null;

                if (! $employeeId) {
                    $unknown[] = ['line' => $row['line'], 'person_id' => $row['person_id'], 'name' => $row['name']];

                    continue;
                }

                $attendance = Attendance::firstOrNew(['employee_id' => $employeeId, 'work_date' => $row['work_date']]);
                $except = $attendance->remark ? ['remark', 'remark_part'] : [];
                $attendance->fill(Arr::except($row, ['line', 'person_id', 'name', 'work_date', ...$except]))->save();

                $attendance->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return ['created' => $created, 'updated' => $updated, 'unknown' => $unknown];
    }

    /**
     * @param  list<string>  $cells
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function parseRow(array $cells, int $line, DateTimeZone $tz, array &$errors): array
    {
        $get = function (string $key) use ($cells): ?string {
            $value = $cells[self::COLUMNS[$key]] ?? '';

            return $value === '' || $value === '-' ? null : $value;
        };

        $personId = $get('person_id');
        if ($personId === null) {
            $errors[] = 'Person ID is missing.';
        } elseif (strlen($personId) > 30) {
            $errors[] = 'Person ID is longer than 30 characters.';
        }

        $date = $get('date');
        $day = $date ? DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz) : false;
        if (! $day || $day->format('Y-m-d') !== $date) {
            $errors[] = 'Date "'.$date.'" is not YYYY-MM-DD.';
            $date = null;
        }

        $clock = function (string $key, string $label) use ($get, $date, $tz, &$errors): ?CarbonImmutable {
            $time = $get($key);
            if ($time === null) {
                return null;
            }
            if (! preg_match('/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time)) {
                $errors[] = $label.' "'.$time.'" is not a time.';

                return null;
            }

            return $date ? CarbonImmutable::parse("{$date} {$time}", $tz) : null;
        };

        $in = $clock('check_in', 'Check-In');
        $out = $clock('check_out', 'Check-out');
        if ($in && $out && $out->lessThan($in)) {
            $out = $out->addDay();
        }

        $row = [
            'line' => $line,
            'person_id' => $personId,
            'name' => $get('name'),
            'work_date' => $date,
            'timetable' => $get('timetable'),
            'status' => $get('status'),
            'clock_in_at' => $in?->utc(),
            'clock_out_at' => $out?->utc(),
        ];

        foreach (self::MINUTE_LABELS as $key => $label) {
            $value = $get($key);
            if ($value !== null && (! ctype_digit($value) || (int) $value > 1440)) {
                $errors[] = $label.' "'.$value.'" is not 0–1440.';
            }
            $row[$key] = $value === null ? null : (int) $value;
        }

        if ($row['status'] !== null && (strlen($row['status']) > 20 || ! preg_match('/^[A-Z0-9]+(-[A-Z0-9]+)*$/', $row['status']))) {
            $errors[] = 'Status "'.$row['status'].'" is not a report code.';
        }

        if ($row['timetable'] !== null && strlen($row['timetable']) > 80) {
            $errors[] = 'Timetable is longer than 80 characters.';
        }

        if ($row['status'] === 'LV') {
            $row['remark'] = 'leave';
            $row['remark_part'] = 'whole';
        }

        return $row;
    }
}
