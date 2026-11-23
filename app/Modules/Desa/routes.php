<?php

declare(strict_types=1);

use App\Modules\Desa\Http\Controllers\DesaDashboardController;
use Illuminate\Support\Facades\Route;

/*
 * Dashboard internal Pemerintah Desa (M2-06).
 */
Route::middleware(['auth', 'village', 'role:desa'])->group(function () {
    Route::get('/desa', [DesaDashboardController::class, 'index'])->name('desa.dashboard');
    Route::get('/desa/dashboard', fn () => redirect()->route('desa.dashboard'));
    Route::get('/audits', fn () => redirect()->route('desa.dashboard'))->name('audits.index');
});
