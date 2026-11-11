<?php

use App\Http\Controllers\Admin\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\User\AttendanceViewController;
use App\Http\Controllers\User\AttendanceCorrectionController;
use App\Http\Controllers\User\CorrectionRequestListController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\StaffController;

Route::get('/', function () {
    return view('user.home');
})->middleware(['auth', 'verified'])->name('home');

Route::get('/admin/login', [AuthController::class, 'showLogin'])
    ->name('admin.login');

Route::post('/admin/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('admin.login.store');

Route::get('/admin', [AuthController::class, 'home'])
    ->middleware(['auth', 'admin'])
    ->name('admin.home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index'])
        ->name('attendance.index');

    Route::post('/attendance', [AttendanceController::class, 'store'])
        ->name('attendance.store');

    Route::get('/attendance/list', [AttendanceViewController::class, 'index'])
        ->name('attendance.list');

    Route::get('/attendance/{id}', [AttendanceViewController::class, 'show'])
        ->whereNumber('id')
        ->name('attendance.show');

    Route::post('/attendance/{id}', [AttendanceCorrectionController::class, 'store'])
        ->whereNumber('id')
        ->name('attendance.correction.store');

    Route::get(
        '/stamp_correction_request/list',
        [CorrectionRequestListController::class, 'index']
    )->name('correction-request.list');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/attendance/list', [AdminAttendanceController::class, 'index'])
        ->name('admin.attendance.list');

    Route::get('/attendance/{id}', [AdminAttendanceController::class, 'show'])
        ->whereNumber('id')
        ->name('admin.attendance.show');

    Route::post('/attendance/{id}', [AdminAttendanceController::class, 'update'])
        ->whereNumber('id')
        ->name('admin.attendance.update');

    Route::get('/staff/list', [StaffController::class, 'index'])
    ->name('admin.staff.list');

    Route::get('/attendance/staff/{id}', [StaffController::class, 'attendance'])
        ->whereNumber('id')
        ->name('admin.staff.attendance');
});
