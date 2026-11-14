<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\InstitutionInvitationController;
use App\Http\Controllers\AcademicCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');
Route::get('/invites/{token}', [InstitutionInvitationController::class, 'show'])->middleware('throttle:30,1')->name('invitations.show');
Route::post('/invites/{token}/accept', [InstitutionInvitationController::class, 'accept'])->middleware('throttle:5,1')->name('invitations.accept');

Route::middleware('guest')->group(function (): void {
    Route::get('/two-factor/challenge', [MfaController::class, 'challenge'])->name('mfa.challenge');
    Route::post('/two-factor/challenge', [MfaController::class, 'completeChallenge'])->middleware('throttle:5,1')->name('mfa.challenge.complete');
    Route::post('/two-factor/cancel', [MfaController::class, 'cancelChallenge'])->name('mfa.challenge.cancel');
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
    Route::get('/account/security/mfa', [MfaController::class, 'setup'])->name('mfa.setup');
    Route::post('/account/security/mfa', [MfaController::class, 'enable'])->middleware('throttle:5,1')->name('mfa.enable');
    Route::get('/account/security/mfa/recovery', [MfaController::class, 'recoveryCodes'])->name('mfa.recovery');
    Route::middleware('privileged.mfa')->group(function (): void {
    Route::post('/dashboard/institutions/{institution:slug}/invitations', [InstitutionInvitationController::class, 'store'])
        ->middleware('throttle:5,1')->name('invitations.store');
    Route::delete('/dashboard/institutions/{institution:slug}/invitations/{invitation}', [InstitutionInvitationController::class, 'revoke'])
        ->middleware('throttle:10,1')->name('invitations.revoke');
    Route::get('/dashboard/institutions/{institution:slug}/academic', [AcademicCatalogController::class, 'index'])
        ->name('academic.index');
    Route::post('/dashboard/institutions/{institution:slug}/academic/years', [AcademicCatalogController::class, 'createYear'])
        ->middleware('throttle:20,1')->name('academic.years.store');
    Route::post('/dashboard/institutions/{institution:slug}/academic/subjects', [AcademicCatalogController::class, 'createSubject'])
        ->middleware('throttle:20,1')->name('academic.subjects.store');
    Route::redirect('/Dashboard', '/dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/institutions/{institution:slug}', [DashboardController::class, 'institution'])
        ->name('dashboard.institution');
    Route::get('/dashboard/institutions/{institution:slug}/campuses/{campus:slug}', [DashboardController::class, 'campus'])
        ->scopeBindings()->name('dashboard.campus');
    Route::get('/dashboard/institutions/{institution:slug}/roles/{role}', [DashboardController::class, 'role'])
        ->name('dashboard.role');
    });
});
