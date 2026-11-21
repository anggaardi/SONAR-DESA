<?php

declare(strict_types=1);

use App\Modules\Report\Http\Controllers\DistanceHintController;
use Illuminate\Support\Facades\Route;

Route::post('/p/{slug}/cek/jarak', DistanceHintController::class)
    ->middleware('throttle:report-distance')
    ->name('reports.distance');
