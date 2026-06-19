<?php

use App\Livewire\Worker\Home;
use App\Livewire\Worker\ProjectShow;
use Illuminate\Support\Facades\Route;

/*
 * Worker portal — `worker` role only. The field-activist mobile app: their
 * projects and the shareholders assigned to them.
 */
Route::middleware(['auth', 'role:worker'])->prefix('app')->name('worker.')->group(function () {
    Route::livewire('/', Home::class)->name('dashboard');
    Route::livewire('projects/{project}', ProjectShow::class)->name('projects.show');
});
