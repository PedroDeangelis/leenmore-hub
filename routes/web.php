<?php

use App\Http\Controllers\ProjectResourceFileController;
use App\Http\Controllers\ReceiptFileController;
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

// A receipt's attachment — served to its owner (worker) or admin/office. The
// controller authorizes per-receipt, so it lives outside the role-scoped groups.
Route::get('receipts/{receipt}/attachment', ReceiptFileController::class)
    ->middleware('auth')
    ->name('receipts.file');

// A project resource's file — served to admin/office or an assigned worker. The
// controller authorizes per-resource, so it lives outside the role-scoped groups.
Route::get('resources/{resource}/file', ProjectResourceFileController::class)
    ->middleware('auth')
    ->name('resources.file');

require __DIR__.'/admin.php';
require __DIR__.'/worker.php';
require __DIR__.'/settings.php';
