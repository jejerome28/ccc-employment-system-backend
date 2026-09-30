<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\EmployeeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $date = $filters['date'] ?? now()->toDateString();

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
                'time_in' => $data['time_in'] ?? null,
                'time_out' => $data['time_out'] ?? null,
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

    public function timeIn(Employee $employee): JsonResponse
    {
        $attendance = Attendance::firstOrNew([
            'employee_id' => $employee->id,
            'work_date' => now()->toDateString(),
        ]);

        if ($attendance->time_in) {
            return ApiResponse::error("{$employee->full_name} already timed in at ".substr($attendance->time_in, 0, 5).'.', 409);
        }

        $attendance->time_in = now()->format('H:i:s');
        $attendance->save();

        return ApiResponse::success('Timed in.', ['attendance' => new AttendanceResource($attendance)]);
    }

    public function timeOut(Employee $employee): JsonResponse
    {
        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('work_date', now()->toDateString())
            ->first();

        if (! $attendance?->time_in) {
            return ApiResponse::error("{$employee->full_name} has not timed in today.", 409);
        }

        if ($attendance->time_out) {
            return ApiResponse::error("{$employee->full_name} already timed out at ".substr($attendance->time_out, 0, 5).'.', 409);
        }

        $attendance->time_out = now()->format('H:i:s');
        $attendance->save();

        return ApiResponse::success('Timed out.', ['attendance' => new AttendanceResource($attendance)]);
    }
}
