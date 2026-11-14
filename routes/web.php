<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\InstitutionInvitationController;
use App\Http\Controllers\AcademicCatalogController;
use App\Http\Controllers\StudentRegistryController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\ClassAttendanceController;
use App\Http\Controllers\ClassTimetableController;
use App\Http\Controllers\ClassAssessmentController;
use App\Http\Controllers\AcademicCorrectionController;
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
    Route::get('/dashboard/institutions/{institution:slug}/students', [StudentRegistryController::class, 'index'])
        ->name('students.index');
    Route::post('/dashboard/institutions/{institution:slug}/students', [StudentRegistryController::class, 'store'])
        ->middleware('throttle:20,1')->name('students.store');
    Route::get('/dashboard/institutions/{institution:slug}/students/{student}', [StudentRegistryController::class, 'show'])
        ->whereNumber('student')->name('students.show');
    Route::post('/dashboard/institutions/{institution:slug}/students/{student}/account', [StudentRegistryController::class, 'linkAccount'])
        ->whereNumber('student')->middleware('throttle:10,1')->name('students.account.link');
    Route::post('/dashboard/institutions/{institution:slug}/students/{student}/guardians', [StudentRegistryController::class, 'verifyGuardian'])
        ->whereNumber('student')->middleware('throttle:10,1')->name('students.guardians.verify');
    Route::delete('/dashboard/institutions/{institution:slug}/students/{student}/guardians/{link}', [StudentRegistryController::class, 'revokeGuardian'])
        ->whereNumber('student')->whereNumber('link')->middleware('throttle:10,1')->name('students.guardians.revoke');
    Route::post('/dashboard/institutions/{institution:slug}/academic/years/{year}/activate', [AcademicCatalogController::class, 'activateYear'])
        ->whereNumber('year')->middleware('throttle:10,1')->name('academic.years.activate');
    Route::get('/dashboard/institutions/{institution:slug}/classes', [ClassroomController::class, 'index'])
        ->name('classrooms.index');
    Route::post('/dashboard/institutions/{institution:slug}/classes', [ClassroomController::class, 'store'])
        ->middleware('throttle:15,1')->name('classrooms.store');
    Route::get('/dashboard/institutions/{institution:slug}/classes/{section}', [ClassroomController::class, 'show'])
        ->whereNumber('section')->name('classrooms.show');
    Route::post('/dashboard/institutions/{institution:slug}/classes/{section}/teacher', [ClassroomController::class, 'assignTeacher'])
        ->whereNumber('section')->middleware('throttle:10,1')->name('classrooms.teacher.assign');
    Route::post('/dashboard/institutions/{institution:slug}/classes/{section}/activate', [ClassroomController::class, 'activate'])
        ->whereNumber('section')->middleware('throttle:10,1')->name('classrooms.activate');
    Route::post('/dashboard/institutions/{institution:slug}/classes/{section}/students', [ClassroomController::class, 'enroll'])
        ->whereNumber('section')->middleware('throttle:20,1')->name('classrooms.students.enroll');
    Route::post('/dashboard/institutions/{institution:slug}/classes/{section}/timetable', [ClassTimetableController::class, 'store'])
        ->whereNumber('section')->middleware('throttle:15,1')->name('timetable.store');
    Route::get('/dashboard/institutions/{institution:slug}/classes/{section}/attendance', [ClassAttendanceController::class, 'index'])
        ->whereNumber('section')->name('attendance.index');
    Route::post('/dashboard/institutions/{institution:slug}/classes/{section}/attendance', [ClassAttendanceController::class, 'store'])
        ->whereNumber('section')->middleware('throttle:15,1')->name('attendance.store');
    Route::get('/dashboard/institutions/{institution:slug}/classes/{section}/attendance/{session}', [ClassAttendanceController::class, 'show'])
        ->whereNumber('section')->whereNumber('session')->name('attendance.show');
    Route::post('/dashboard/institutions/{institution:slug}/classes/{section}/attendance/{session}/marks', [ClassAttendanceController::class, 'mark'])
        ->whereNumber('section')->whereNumber('session')->middleware('throttle:120,1')->name('attendance.mark');
    Route::post('/dashboard/institutions/{institution:slug}/classes/{section}/attendance/{session}/submit', [ClassAttendanceController::class, 'submit'])
        ->whereNumber('section')->whereNumber('session')->middleware('throttle:15,1')->name('attendance.submit');
    Route::post('/dashboard/institutions/{institution:slug}/classes/{section}/attendance/{session}/approve', [ClassAttendanceController::class, 'approve'])
        ->whereNumber('section')->whereNumber('session')->middleware('throttle:10,1')->name('attendance.approve');
    Route::get('/dashboard/institutions/{institution:slug}/classes/{section}/assessments', [ClassAssessmentController::class, 'index'])
        ->whereNumber('section')->name('assessments.index');
    Route::post('/dashboard/institutions/{institution:slug}/classes/{section}/assessments', [ClassAssessmentController::class, 'store'])
        ->whereNumber('section')->middleware('throttle:15,1')->name('assessments.store');
    Route::get('/dashboard/institutions/{institution:slug}/classes/{section}/assessments/{assessment}', [ClassAssessmentController::class, 'show'])
        ->whereNumber('section')->whereNumber('assessment')->name('assessments.show');
    Route::post('/dashboard/institutions/{institution:slug}/classes/{section}/assessments/{assessment}/results', [ClassAssessmentController::class, 'record'])
        ->whereNumber('section')->whereNumber('assessment')->middleware('throttle:120,1')->name('assessments.record');
    Route::post('/dashboard/institutions/{institution:slug}/classes/{section}/assessments/{assessment}/submit', [ClassAssessmentController::class, 'submit'])
        ->whereNumber('section')->whereNumber('assessment')->middleware('throttle:15,1')->name('assessments.submit');
    Route::post('/dashboard/institutions/{institution:slug}/classes/{section}/assessments/{assessment}/publish', [ClassAssessmentController::class, 'publish'])
        ->whereNumber('section')->whereNumber('assessment')->middleware('throttle:10,1')->name('assessments.publish');
    Route::post('/dashboard/institutions/{institution:slug}/classes/{section}/corrections', [AcademicCorrectionController::class, 'store'])
        ->whereNumber('section')->middleware('throttle:25,1')->name('corrections.store');
    Route::post('/dashboard/institutions/{institution:slug}/classes/{section}/corrections/{correction}/review', [AcademicCorrectionController::class, 'review'])
        ->whereNumber('section')->whereNumber('correction')->middleware('throttle:15,1')->name('corrections.review');
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
