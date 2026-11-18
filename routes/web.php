<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Feature routes live in app/Modules/<Module>/routes.php (ModuleServiceProvider).
Route::get('/', fn () => Inertia::render('core/Home'))->name('home');
