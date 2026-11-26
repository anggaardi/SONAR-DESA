<?php

declare(strict_types=1);

use App\Modules\Demo\Http\DemoController;
use Illuminate\Support\Facades\Route;

Route::get('/demo', [DemoController::class, 'index'])->name('demo');
Route::post('/demo/start/{role}', [DemoController::class, 'start'])->middleware('throttle:10,1')->name('demo.start');
Route::post('/demo/switch/{role}', [DemoController::class, 'switch'])->name('demo.switch');
Route::post('/demo/reset', [DemoController::class, 'reset'])->middleware('throttle:10,1')->name('demo.reset');
Route::post('/demo/contoh-rab/{sample}', [DemoController::class, 'sampleRab'])
    ->whereIn('sample', ['1', '2', '3'])
    ->middleware('throttle:10,1')
    ->name('demo.sample-rab');
Route::get('/demo/lokasi-contoh/{slug}', [DemoController::class, 'sampleLocation'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('demo.sample-location');
