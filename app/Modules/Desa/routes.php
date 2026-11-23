<?php

declare(strict_types=1);

use App\Modules\Desa\Http\Controllers\DesaDashboardController;
use App\Modules\Desa\Http\Controllers\DesaProjectController;
use App\Modules\Desa\Http\Controllers\DesaProjectManageController;
use Illuminate\Support\Facades\Route;

/*
 * Dashboard internal Pemerintah Desa (M2-06) & Kelola Proyek / CCO (Story S4).
 */
Route::middleware(['auth', 'village', 'role:desa'])->group(function () {
    Route::get('/desa', [DesaDashboardController::class, 'index'])->name('desa.dashboard');
    Route::get('/desa/dashboard', fn () => redirect()->route('desa.dashboard'));
    Route::get('/audits', fn () => redirect()->route('audits.create'))->name('audits.index');
    Route::get('/desa/projects/create', [DesaProjectController::class, 'create'])->name('desa.projects.create');
    Route::post('/desa/projects', [DesaProjectController::class, 'store'])->name('desa.projects.store');

    // Kelola Proyek & CCO (Story S4, D10-D14)
    Route::get('/desa/projects/{project}', [DesaProjectManageController::class, 'show'])->name('desa.projects.show');
    Route::delete('/desa/projects/{project}', [DesaProjectManageController::class, 'destroy'])->name('desa.projects.destroy');
    Route::delete('/desa/projects/{project}/measurements/{measurement}', [DesaProjectManageController::class, 'destroyMeasurement'])->name('desa.projects.measurements.destroy');
    Route::post('/desa/projects/{project}/cco/items/{item}', [DesaProjectManageController::class, 'updateItemCco'])->name('desa.projects.cco.items.update');
    Route::post('/desa/projects/{project}/cco/measurements/{measurement}', [DesaProjectManageController::class, 'updateMeasurementCco'])->name('desa.projects.cco.measurements.update');
    Route::post('/desa/projects/{project}/cco/gps', [DesaProjectManageController::class, 'updateGpsCco'])->name('desa.projects.cco.gps.update');
});
