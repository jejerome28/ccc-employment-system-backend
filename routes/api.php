<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('api.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('api.dashboard');

    Route::apiResource('employees', EmployeeController::class)->names('api.employees');
    Route::post('/employees/{employee}/time-in', [AttendanceController::class, 'timeIn'])->name('api.attendance.time-in');
    Route::post('/employees/{employee}/time-out', [AttendanceController::class, 'timeOut'])->name('api.attendance.time-out');
    Route::apiResource('attendance', AttendanceController::class)
        ->parameters(['attendance' => 'attendance'])
        ->names('api.attendance');
});
