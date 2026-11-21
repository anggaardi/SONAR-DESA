<?php

declare(strict_types=1);

use App\Modules\Core\Auth\Http\LoginController;
use Illuminate\Support\Facades\Route;

// No `guest` middleware: demo accounts must reach the form; LoginController sends real accounts home.
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
