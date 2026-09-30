<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClockRequest;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\EmployeeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $date = $filters['date'];

        $attendances = Attendance::query()
            ->with('employee')
            ->where('work_date', $date)
            ->when($filters['q'] ?? null, function ($query, $term) {
                $query->whereHas('employee', fn ($q) => $q->search($term));
            })
            ->get()
            ->sortBy(fn (Attendance $a) => $a->employee->last_name)
            ->values();

        return ApiResponse::success('OK', [
            'attendances' => AttendanceResource::collection($attendances),
            'total_minutes' => $attendances->sum(fn (Attendance $a) => $a->worked_minutes ?? 0),
            'employees' => EmployeeResource::collection(Employee::active()->orderBy('last_name')->get()),
        ]);
    }

    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $attendance = Attendance::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'work_date' => $data['work_date']],
            [
                'clock_in_at' => $data['clock_in_at'] ?? null,
                'clock_out_at' => $data['clock_out_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
        );

        return ApiResponse::success('Attendance saved.', ['attendance' => new AttendanceResource($attendance->load('employee'))]);
    }

    public function show(Attendance $attendance): JsonResponse
    {
        return ApiResponse::success('OK', ['attendance' => new AttendanceResource($attendance->load('employee'))]);
    }

    public function update(UpdateAttendanceRequest $request, Attendance $attendance): JsonResponse
    {
        $attendance->update($request->validated());

        return ApiResponse::success('Attendance updated.', ['attendance' => new AttendanceResource($attendance->load('employee'))]);
    }

    public function destroy(Attendance $attendance): JsonResponse
    {
        $attendance->delete();

        return ApiResponse::success('Attendance deleted.');
    }

    public function timeIn(ClockRequest $request, Employee $employee): JsonResponse
    {
        $attendance = Attendance::firstOrNew([
            'employee_id' => $employee->id,
            'work_date' => $request->validated('work_date'),
        ]);

        if ($attendance->clock_in_at) {
            return ApiResponse::error("{$employee->full_name} already timed in at ".self::wallClock($attendance->clock_in_at).'.', 409);
        }

        $attendance->clock_in_at = now()->utc();
        $attendance->save();

        return ApiResponse::success('Timed in.', ['attendance' => new AttendanceResource($attendance)]);
    }

    public function timeOut(ClockRequest $request, Employee $employee): JsonResponse
    {
        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('work_date', $request->validated('work_date'))
            ->first();

        if (! $attendance?->clock_in_at) {
            return ApiResponse::error("{$employee->full_name} has not timed in today.", 409);
        }

        if ($attendance->clock_out_at) {
            return ApiResponse::error("{$employee->full_name} already timed out at ".self::wallClock($attendance->clock_out_at).'.', 409);
        }

        $attendance->clock_out_at = now()->utc();
        $attendance->save();

        return ApiResponse::success('Timed out.', ['attendance' => new AttendanceResource($attendance)]);
    }

    private static function wallClock(CarbonInterface $moment): string
    {
        return $moment->setTimezone(config('attendance.timezone'))->format('g:i A');
    }
}
