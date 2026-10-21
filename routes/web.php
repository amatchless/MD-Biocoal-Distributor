<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealerController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

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

    Route::middleware('role:'.User::SUPER_ADMIN)->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
