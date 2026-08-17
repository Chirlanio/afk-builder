<?php

use App\Http\Controllers\Settings\BackupController;
use App\Http\Controllers\Settings\GameAccountController;
use App\Http\Controllers\Settings\LocaleController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::patch('settings/locale', [LocaleController::class, 'update'])->name('locale.update');

    Route::get('settings/accounts', [GameAccountController::class, 'index'])->name('accounts.index');
    Route::post('settings/accounts', [GameAccountController::class, 'store'])->name('accounts.store');
    Route::patch('settings/accounts/{account}', [GameAccountController::class, 'update'])->name('accounts.update');
    Route::delete('settings/accounts/{account}', [GameAccountController::class, 'destroy'])->name('accounts.destroy');

    Route::get('settings/backup', [BackupController::class, 'show'])->name('backup.show');
    Route::get('settings/backup/download', [BackupController::class, 'download'])->name('backup.download');
    Route::post('settings/backup/import', [BackupController::class, 'import'])->name('backup.import');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');
});
