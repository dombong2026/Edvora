<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'emailResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::redirect('/Dashboard', '/dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/institutions/{institution:slug}', [DashboardController::class, 'institution'])
        ->name('dashboard.institution');
    Route::get('/dashboard/institutions/{institution:slug}/campuses/{campus:slug}', [DashboardController::class, 'campus'])
        ->scopeBindings()->name('dashboard.campus');
    Route::get('/dashboard/institutions/{institution:slug}/roles/{role}', [DashboardController::class, 'role'])
        ->name('dashboard.role');
});
