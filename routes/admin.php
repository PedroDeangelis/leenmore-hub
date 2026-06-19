<?php

use App\Http\Controllers\ShareholderTemplateController;
use App\Livewire\Projects\Create as ProjectsCreate;
use App\Livewire\Projects\Index as ProjectsIndex;
use App\Livewire\Projects\Show as ProjectsShow;
use App\Livewire\Users\Index as UsersIndex;
use App\Livewire\Users\Show as UsersShow;
use Illuminate\Support\Facades\Route;

/*
 * Admin area — shared by the `admin` and `office` roles.
 * Admin-only routes additionally use Gate middleware, e.g. ->middleware('can:manage-users').
 */
Route::middleware(['auth', 'role:admin,office'])->prefix('dashboard')->group(function () {
    Route::view('/', 'dashboard')->name('dashboard');

    Route::livewire('users', UsersIndex::class)
        ->middleware('can:manage-users')
        ->name('users.index');

    Route::livewire('users/{user}', UsersShow::class)
        ->middleware('can:manage-users')
        ->name('users.show');

    // Projects: admin + office may view; only admin may create/edit/archive.
    // `projects/create` is declared before `projects/{project}` so it is not
    // captured as a project id. Editing happens inline on the show page.
    Route::livewire('projects', ProjectsIndex::class)
        ->middleware('can:view-projects')
        ->name('projects.index');

    Route::livewire('projects/create', ProjectsCreate::class)
        ->middleware('can:manage-projects')
        ->name('projects.create');

    Route::livewire('projects/{project}', ProjectsShow::class)
        ->middleware('can:view-projects')
        ->name('projects.show');

    // Downloadable sample for the shareholder importer.
    Route::get('shareholders/template', ShareholderTemplateController::class)
        ->middleware('can:manage-shareholders')
        ->name('shareholders.template');
});
