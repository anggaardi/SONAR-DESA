<?php

declare(strict_types=1);

use App\Modules\Review\Http\Controllers\BpdDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'village'])->prefix('bpd')->name('bpd.')->group(function () {
    Route::get('/', [BpdDashboardController::class, 'index'])->name('index');
    Route::post('/reports/{reportId}/open', [BpdDashboardController::class, 'openReport'])->name('reports.open');
    Route::post('/reports/{reportId}/invalidate', [BpdDashboardController::class, 'invalidateReport'])->name('reports.invalidate');
    Route::post('/reports/{reportId}/restore', [BpdDashboardController::class, 'restoreReport'])->name('reports.restore');
    Route::post('/projects/{projectId}/outcome', [BpdDashboardController::class, 'storeOutcome'])->name('projects.outcome');
    Route::post('/cycles/{cycleId}/notes', [BpdDashboardController::class, 'storeFollowUpNote'])->name('cycles.notes');
});
