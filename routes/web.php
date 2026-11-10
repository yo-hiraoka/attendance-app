<?php

use App\Http\Controllers\Admin\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\User\AttendanceViewController;

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
});
