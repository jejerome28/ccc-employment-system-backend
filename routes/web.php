<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

/*
 * Login only. There is deliberately no registration route - accounts come
 * from the seeder or from `php artisan tinker`.
 */
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('employees', EmployeeController::class)->only(['index', 'show', 'create', 'edit']);
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/{attendance}/edit', [AttendanceController::class, 'edit'])->name('attendance.edit');

    Route::middleware('role:admin,hr')->group(function () {
        Route::resource('employees', EmployeeController::class)->only(['store', 'update']);
        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::post('/attendance/{employee}/time-in', [AttendanceController::class, 'timeIn'])->name('attendance.time-in');
        Route::post('/attendance/{employee}/time-out', [AttendanceController::class, 'timeOut'])->name('attendance.time-out');
        Route::put('/attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update');
        Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');
    });

    Route::middleware('role:admin')->group(function () {
        Route::resource('employees', EmployeeController::class)->only(['destroy']);
    });
});
