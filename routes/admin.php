<?php

use App\Http\Controllers\ReportFileController;
use App\Http\Controllers\ShareholderTemplateController;
use App\Livewire\Activity\Index as ActivityIndex;
use App\Livewire\Activity\Report as ActivityReport;
use App\Livewire\Activity\Roster as ActivityRoster;
use App\Livewire\Projects\Create as ProjectsCreate;
use App\Livewire\Projects\Index as ProjectsIndex;
use App\Livewire\Projects\Show as ProjectsShow;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Reports\Show as ReportsShow;
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

    // Reports archive: admin + office may read activity reports. The file route
    // is declared before `reports/{project}` so it is not captured as a project.
    Route::livewire('reports', ReportsIndex::class)
        ->middleware('can:view-submissions')
        ->name('reports.index');

    Route::get('reports/files/{submission}/{index}', ReportFileController::class)
        ->whereNumber('index')
        ->middleware('can:view-submissions')
        ->name('reports.file');

    Route::livewire('reports/{project}', ReportsShow::class)
        ->whereNumber('project')
        ->middleware('can:view-submissions')
        ->name('reports.show');

    // Activity reports: admin + office manually file reports on a worker's behalf
    // (projects → shareholders → report page).
    Route::livewire('activity', ActivityIndex::class)
        ->middleware('can:edit-submissions')
        ->name('activity.index');

    Route::livewire('activity/{project}', ActivityRoster::class)
        ->whereNumber('project')
        ->middleware('can:edit-submissions')
        ->name('activity.project');

    Route::livewire('activity/{project}/{projectShareholder}', ActivityReport::class)
        ->whereNumber('project')
        ->whereNumber('projectShareholder')
        ->middleware('can:edit-submissions')
        ->name('activity.report');
});
