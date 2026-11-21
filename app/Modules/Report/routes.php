<?php

declare(strict_types=1);

use App\Modules\Report\Http\Controllers\DistanceHintController;
use App\Modules\Report\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/p/{slug}/cek/jarak', DistanceHintController::class)
    ->middleware('throttle:report-distance')
    ->name('reports.distance');

Route::middleware(['guest', 'throttle:20,1'])->group(function () {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});
