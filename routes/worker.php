<?php

use App\Livewire\Worker\ActivityReport;
use App\Livewire\Worker\Home;
use App\Livewire\Worker\ProjectShow;
use Illuminate\Support\Facades\Route;

/*
 * Worker portal — `worker` role only. The field-activist mobile app: their
 * projects, the shareholders assigned to them, and the activity report they
 * file per shareholder.
 */
Route::middleware(['auth', 'role:worker'])->prefix('app')->name('worker.')->group(function () {
    Route::livewire('/', Home::class)->name('dashboard');
    Route::livewire('projects/{project}', ProjectShow::class)->name('projects.show');
    Route::livewire('projects/{project}/shareholders/{projectShareholder}/report', ActivityReport::class)
        ->name('projects.shareholders.report');
});
