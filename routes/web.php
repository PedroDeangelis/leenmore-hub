<?php

use Illuminate\Support\Facades\Route;

/*
 * Like the legacy React app, the root is the login page: guests are sent
 * to /login, authenticated users to their own area.
 */
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route(
            auth()->user()->role->worksInAdminArea() ? 'dashboard' : 'worker.dashboard'
        );
    }

    return redirect()->route('login');
})->name('home');

require __DIR__.'/admin.php';
require __DIR__.'/worker.php';
require __DIR__.'/settings.php';
