<?php

declare(strict_types=1);

use App\Modules\Project\Http\Controllers\OriginalDocumentController;
use App\Modules\Project\Http\Controllers\ProgressPhotoController;
use App\Modules\Project\Http\Controllers\ProjectController;
use App\Modules\Project\Http\Controllers\ProjectQrController;
use App\Modules\Project\Http\Controllers\PublishedReportPhotoController;
use App\Modules\Project\Http\Controllers\VillageProjectController;
use Illuminate\Support\Facades\Route;

Route::get('/p/{slug}', [ProjectController::class, 'show'])->name('projects.show');
Route::get('/d/{village}', [VillageProjectController::class, 'index'])->name('villages.projects');
Route::get('/p/{slug}/dokumen', [OriginalDocumentController::class, 'show'])->name('projects.document');
Route::get('/media/progress/{photo}', [ProgressPhotoController::class, 'show'])->name('media.progress');
Route::get('/media/reports/{photoId}', [PublishedReportPhotoController::class, 'show'])
    ->whereNumber('photoId')
    ->name('media.report-photo');

Route::middleware(['auth', 'village'])->group(function () {
    Route::post('/projects/{project}/progress', [ProgressPhotoController::class, 'store'])->name('projects.progress.store');
    Route::get('/projects/{project}/qr', [ProjectQrController::class, 'show'])->name('projects.qr');
});
