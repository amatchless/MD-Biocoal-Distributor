<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CandidateOnboardingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealerController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeOnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// The candidate's private onboarding link (no login).
Route::middleware('throttle:60,1')->prefix('join/{token}')->group(function () {
    Route::get('/', [CandidateOnboardingController::class, 'show'])->name('candidate.show');
    Route::post('/', [CandidateOnboardingController::class, 'save'])->name('candidate.save');
    Route::delete('/documents/{document}', [CandidateOnboardingController::class, 'destroyDocument'])->name('candidate.documents.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/dealers/export', [DealerController::class, 'export'])->name('dealers.export');
    Route::get('/dealers/duplicate-check', [DealerController::class, 'duplicateCheck'])->name('dealers.duplicate-check');
    Route::resource('dealers', DealerController::class)->except('destroy');
    Route::post('/dealers/{dealer}/review', [DealerController::class, 'review'])->name('dealers.review');
    Route::post('/dealers/{dealer}/documents', [DealerController::class, 'addDocuments'])->name('dealers.documents.store');
    Route::get('/dealers/{dealer}/documents/{document}', [DealerController::class, 'document'])->name('dealers.documents.show');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/team', [AttendanceController::class, 'team'])->name('attendance.team');
    Route::get('/attendance/{attendance}/photo', [AttendanceController::class, 'photo'])->name('attendance.photo');

    Route::prefix('new-hires')->name('onboarding.')->controller(EmployeeOnboardingController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{onboarding}', 'show')->name('show');
        Route::get('/{onboarding}/edit', 'edit')->name('edit');
        Route::put('/{onboarding}', 'update')->name('update');
        Route::post('/{onboarding}/link', 'issueLink')->name('link');
        Route::post('/{onboarding}/request-info', 'requestInfo')->name('request-info');
        Route::post('/{onboarding}/documents', 'addDocuments')->name('documents.store');
        Route::get('/{onboarding}/documents/{document}', 'document')->name('documents.show');
        Route::post('/{onboarding}/checks/{check}', 'updateCheck')->name('checks.update');
        Route::post('/{onboarding}/references', 'storeReference')->name('references.store');
        Route::post('/{onboarding}/assess', 'assess')->name('assess');
        Route::post('/{onboarding}/decide', 'decide')->name('decide');
        Route::get('/{onboarding}/report', 'report')->name('report');
        Route::post('/{onboarding}/account', 'createAccount')->name('account');
    });

    Route::middleware('role:'.User::SUPER_ADMIN)->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
