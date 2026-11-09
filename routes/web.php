<?php

use App\Http\Controllers\Admin\AuthController;
use Illuminate\Support\Facades\Route;

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
