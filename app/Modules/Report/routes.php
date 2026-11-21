<?php

declare(strict_types=1);

use App\Modules\Report\Http\Controllers\CheckSpecController;
use App\Modules\Report\Http\Controllers\DistanceHintController;
use App\Modules\Report\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

// Guests are redirected to Google, not the desa/BPD login form (bootstrap/app.php).
Route::get('/p/{slug}/cek', [CheckSpecController::class, 'show'])->middleware('auth')->name('reports.check');

Route::post('/p/{slug}/cek/jarak', DistanceHintController::class)
    ->middleware('throttle:report-distance')
    ->name('reports.distance');

Route::middleware(['guest', 'throttle:20,1'])->group(function () {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});
