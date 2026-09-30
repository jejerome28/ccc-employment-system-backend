<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];

        $employees = Employee::active()
            ->with(['dayAttendance' => fn ($q) => $q->where('work_date', $date)])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $clockedIn = $employees->filter(fn ($e) => $e->dayAttendance?->clock_in_at && ! $e->dayAttendance?->clock_out_at)->count();
        $completed = $employees->filter(fn ($e) => $e->dayAttendance?->clock_out_at)->count();

        $minutesToday = Attendance::where('work_date', $date)->get()
            ->sum(fn (Attendance $a) => $a->worked_minutes ?? 0);

        return ApiResponse::success('OK', [
            'stats' => [
                'employees' => Employee::count(),
                'active' => $employees->count(),
                'clocked_in' => $clockedIn,
                'completed' => $completed,
                'not_in' => $employees->count() - $clockedIn - $completed,
            ],
            'hours_today' => round($minutesToday / 60, 2),
            'employees' => EmployeeResource::collection($employees),
        ]);
    }
}
