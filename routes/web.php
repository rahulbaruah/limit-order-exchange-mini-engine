<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('/', 'Dashboard')->name('home');
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('orders/create', 'Orders/Create')->name('orders.create');
});

require __DIR__.'/settings.php';
