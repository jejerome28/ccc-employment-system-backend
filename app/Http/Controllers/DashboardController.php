<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = now()->toDateString();

        $employees = Employee::active()
            ->with('todayAttendance')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $stats = [
            'employees' => Employee::count(),
            'active' => $employees->count(),
            'clocked_in' => $employees->filter(
                fn ($e) => $e->todayAttendance?->time_in && ! $e->todayAttendance?->time_out
            )->count(),
            'completed' => $employees->filter(
                fn ($e) => $e->todayAttendance?->time_out
            )->count(),
        ];

        $stats['not_in'] = $stats['active'] - $stats['clocked_in'] - $stats['completed'];

        $hoursToday = Attendance::whereDate('work_date', $today)->get()
            ->sum(fn (Attendance $a) => $a->worked_minutes ?? 0) / 60;

        return view('dashboard', [
            'employees' => $employees,
            'stats' => $stats,
            'hoursToday' => round($hoursToday, 2),
            'today' => now(),
        ]);
    }
}
