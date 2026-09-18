<?php

use App\Livewire\Api\Docs as ApiDocs;
use App\Livewire\Api\Tokens as ApiTokens;
use App\Livewire\Users\Index as UsersIndex;
use App\Livewire\Users\Show as UsersShow;
use Illuminate\Support\Facades\Route;

/*
 * The whole web surface: an API portal and the handful of accounts that may use
 * it. Everything the hub used to be — projects, shareholders, activity reports,
 * receipts, resources, the worker portal — is gone; Supabase remains the system
 * of record and this app only reads from it (see routes/api.php).
 *
 * Guests land on the login page; everyone else lands on the API portal, which is
 * the only thing here worth landing on.
 */
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('api.tokens')
        : redirect()->route('login');
})->name('home');

Route::middleware('auth')->prefix('dashboard')->group(function (): void {
    /*
     * REST API portal: token management (the landing page) and one docs+tester
     * page per endpoint. Viewers may read the docs and test with a key assigned
     * to them; minting and revoking keys is admin-only, enforced inside the
     * component rather than here so a viewer can still reach the page.
     */
    Route::livewire('api', ApiTokens::class)
        ->middleware('can:use-api-portal')
        ->name('api.tokens');

    Route::livewire('api/endpoints/{endpoint}', ApiDocs::class)
        ->middleware('can:use-api-portal')
        ->name('api.docs');

    // Login accounts for this portal. Admin-only.
    Route::livewire('users', UsersIndex::class)
        ->middleware('can:manage-users')
        ->name('users.index');

    Route::livewire('users/{user}', UsersShow::class)
        ->middleware('can:manage-users')
        ->name('users.show');
});
