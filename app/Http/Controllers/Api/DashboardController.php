<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $employees = Employee::active()
            ->with('todayAttendance')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $clockedIn = $employees->filter(fn ($e) => $e->todayAttendance?->time_in && ! $e->todayAttendance?->time_out)->count();
        $completed = $employees->filter(fn ($e) => $e->todayAttendance?->time_out)->count();

        $minutesToday = Attendance::where('work_date', now()->toDateString())->get()
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
