<?php

declare(strict_types=1);

use App\Modules\Report\Http\Controllers\CheckSpecController;
use App\Modules\Report\Http\Controllers\DistanceHintController;
use App\Modules\Report\Http\Controllers\GoogleAuthController;
use App\Modules\Report\Http\Controllers\ReportController;
use App\Modules\Report\Http\Middleware\EnsureDeviceCookie;
use Illuminate\Support\Facades\Route;

// Guests are redirected to Google, not the desa/BPD login form (bootstrap/app.php).
// The device cookie is set on the checklist page already, so the send carries it (D-010).
Route::get('/p/{slug}/cek', [CheckSpecController::class, 'show'])
    ->middleware(['auth', EnsureDeviceCookie::class])
    ->name('reports.check');

Route::post('/p/{slug}/reports', [ReportController::class, 'store'])
    ->middleware(['auth', 'throttle:report-submit', EnsureDeviceCookie::class])
    ->name('reports.store');
Route::get('/p/{slug}/reports/terkirim', [ReportController::class, 'submitted'])->middleware('auth')->name('reports.submitted');

Route::post('/p/{slug}/cek/jarak', DistanceHintController::class)
    ->middleware('throttle:report-distance')
    ->name('reports.distance');

Route::middleware(['guest', 'throttle:20,1'])->group(function () {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});
