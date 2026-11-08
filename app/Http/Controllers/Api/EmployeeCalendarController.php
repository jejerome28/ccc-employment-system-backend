<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeCalendarRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Employee;
use App\Services\AttendanceCalendar;
use Illuminate\Http\JsonResponse;

class EmployeeCalendarController extends Controller
{
    public function __invoke(EmployeeCalendarRequest $request, Employee $employee, AttendanceCalendar $calendar): JsonResponse
    {
        return ApiResponse::success('OK', $calendar->month($employee, $request->validated('month')));
    }
}
