<?php

declare(strict_types=1);

use App\Modules\Review\Http\Controllers\BpdDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'village'])->prefix('bpd')->name('bpd.')->group(function () {
    Route::get('/', [BpdDashboardController::class, 'index'])->name('index');
    Route::get('/reports', [BpdDashboardController::class, 'reportsIndex'])->name('reports.index');
    Route::get('/photos/{photoId}', [BpdDashboardController::class, 'showPhoto'])->name('photos.show');
    Route::post('/reports/{reportId}/open', [BpdDashboardController::class, 'openReport'])->name('reports.open');
    Route::post('/reports/{reportId}/validate', [BpdDashboardController::class, 'validateReport'])->name('reports.validate');
    Route::post('/reports/{reportId}/reply', [BpdDashboardController::class, 'replyReport'])->name('reports.reply');
    Route::post('/projects/{projectId}/reports/open-all', [BpdDashboardController::class, 'openAllReports'])->name('projects.reports.open-all');
    Route::post('/reports/{reportId}/invalidate', [BpdDashboardController::class, 'invalidateReport'])->name('reports.invalidate');
    Route::post('/projects/{projectId}/outcome', [BpdDashboardController::class, 'storeOutcome'])->name('projects.outcome');
    Route::post('/cycles/{cycleId}/notes', [BpdDashboardController::class, 'storeFollowUpNote'])->name('cycles.notes');
});
