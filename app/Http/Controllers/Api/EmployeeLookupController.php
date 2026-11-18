<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeLookupRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Employee;
use App\Services\AttendanceCalendar;
use App\Support\AttendanceDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class EmployeeLookupController extends Controller
{
    public function __invoke(EmployeeLookupRequest $request, AttendanceCalendar $calendar): JsonResponse
    {
        $month = $request->validated('month') ?? AttendanceDate::thisMonth();
        $employee = Employee::whereRaw('upper(employee_code) = ?', [mb_strtoupper($request->validated('employee_code'))])->first();
        abort_unless($employee, 404, 'No employee with that employee code.');

        [$year, $monthNumber] = explode('-', $month);
        $attendances = $employee->attendances()
            ->whereYear('work_date', (int) $year)
            ->whereMonth('work_date', (int) $monthNumber)
            ->orderByDesc('work_date')
            ->get();

        return ApiResponse::success('OK', [
            'employee' => [
                'full_name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
                'person_id' => $employee->biometric_id,
                'position' => $employee->position,
                'department' => $employee->department,
                'employment_group' => $employee->employment_group?->value,
                'status' => $employee->status,
            ],
            'calendar' => Arr::except($calendar->month($employee, $month), ['employee']),
            'attendances' => $attendances->map(fn ($a) => Arr::except((new AttendanceResource($a))->resolve($request), ['employee_id']))->values(),
            'total_minutes' => $attendances->sum(fn ($a) => $a->worked_minutes ?? 0),
            'days_present' => $attendances->whereNotNull('clock_in_at')->count(),
        ]);
    }
}
