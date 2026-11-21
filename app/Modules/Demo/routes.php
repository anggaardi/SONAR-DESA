<?php

declare(strict_types=1);

use App\Modules\Demo\Http\DemoController;
use Illuminate\Support\Facades\Route;

Route::get('/demo', [DemoController::class, 'index'])->name('demo');
Route::post('/demo/start/{role}', [DemoController::class, 'start'])->middleware('throttle:10,1')->name('demo.start');
Route::post('/demo/switch/{role}', [DemoController::class, 'switch'])->name('demo.switch');
Route::post('/demo/reset', [DemoController::class, 'reset'])->middleware('throttle:10,1')->name('demo.reset');
