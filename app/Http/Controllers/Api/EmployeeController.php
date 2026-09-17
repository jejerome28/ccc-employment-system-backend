<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\EmployeeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $page = Employee::query()
            ->search($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->with('todayAttendance')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(12);

        return ApiResponse::success('OK', [
            'items' => EmployeeResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(EmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());

        return ApiResponse::success('Employee created.', ['employee' => new EmployeeResource($employee)], 201);
    }

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $month = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? now()->format('Y-m');
        [$year, $monthNumber] = explode('-', $month);

        $attendances = $employee->attendances()
            ->whereYear('work_date', (int) $year)
            ->whereMonth('work_date', (int) $monthNumber)
            ->orderByDesc('work_date')
            ->get();

        return ApiResponse::success('OK', [
            'employee' => new EmployeeResource($employee),
            'attendances' => AttendanceResource::collection($attendances),
            'total_minutes' => $attendances->sum(fn ($a) => $a->worked_minutes ?? 0),
            'days_present' => $attendances->whereNotNull('time_in')->count(),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee->update($request->validated());

        return ApiResponse::success('Employee updated.', ['employee' => new EmployeeResource($employee)]);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return ApiResponse::success('Employee deleted.');
    }
}
