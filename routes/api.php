<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceExportController;
use App\Http\Controllers\Api\AttendanceImportController;
use App\Http\Controllers\Api\AttendanceSummaryController;
use App\Http\Controllers\Api\AttendanceSummaryExportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmployeeCalendarController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('api.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('api.dashboard');

    Route::apiResource('employees', EmployeeController::class)->only(['index', 'show'])->names('api.employees');
    Route::get('/employees/{employee}/calendar', EmployeeCalendarController::class)->name('api.employees.calendar');
    Route::get('/attendance/export', AttendanceExportController::class)->name('api.attendance.export');
    Route::get('/attendance/summary', AttendanceSummaryController::class)->name('api.attendance.summary');
    Route::get('/attendance/summary/export', AttendanceSummaryExportController::class)->name('api.attendance.summary.export');
    Route::apiResource('attendance', AttendanceController::class)->only(['index', 'show'])
        ->parameters(['attendance' => 'attendance'])
        ->names('api.attendance');

    Route::middleware('role:admin,hr')->group(function () {
        Route::apiResource('employees', EmployeeController::class)->only(['store', 'update'])->names('api.employees');
        Route::post('/employees/{employee}/time-in', [AttendanceController::class, 'timeIn'])->name('api.attendance.time-in');
        Route::post('/employees/{employee}/time-out', [AttendanceController::class, 'timeOut'])->name('api.attendance.time-out');
        Route::apiResource('attendance', AttendanceController::class)->only(['store', 'update', 'destroy'])
            ->parameters(['attendance' => 'attendance'])
            ->names('api.attendance');
    });

    Route::middleware('role:admin')->group(function () {
        Route::apiResource('employees', EmployeeController::class)->only(['destroy'])->names('api.employees');
        Route::post('/employees/{employee}/restore', [EmployeeController::class, 'restore'])->withTrashed()->name('api.employees.restore');
        Route::apiResource('users', UserController::class)->only(['index', 'store', 'update'])->names('api.users');
        Route::post('/attendance/import', AttendanceImportController::class)->name('api.attendance.import');
    });
});
