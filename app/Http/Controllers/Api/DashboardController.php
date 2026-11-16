<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Attendance;
use App\Models\Employee;
use App\Support\AttendanceDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $input = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.AttendanceDate::today()],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $date = $input['date'] ?? Attendance::defaultDate();

        $page = Employee::active()
            ->search($input['q'] ?? null)
            ->with(['dayAttendance' => fn ($q) => $q->where('work_date', $date)])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25);

        $day = fn () => Attendance::where('work_date', $date)->whereHas('employee', fn ($q) => $q->active()->whereNull('employees.deleted_at'));
        $active = Employee::active()->count();
        $clockedIn = $day()->whereNotNull('clock_in_at')->whereNull('clock_out_at')->count();
        $completed = $day()->whereNotNull('clock_out_at')->count();

        $minutesToday = Attendance::where('work_date', $date)->get()
            ->sum(fn (Attendance $a) => $a->worked_minutes ?? 0);

        return ApiResponse::success('OK', [
            'date' => $date,
            'stats' => [
                'employees' => Employee::count(),
                'active' => $active,
                'clocked_in' => $clockedIn,
                'completed' => $completed,
                'not_in' => $active - $clockedIn - $completed,
            ],
            'hours_today' => round($minutesToday / 60, 2),
            'employees' => EmployeeResource::collection($page->items()),
            'meta' => ApiResponse::meta($page),
        ]);
    }
}
