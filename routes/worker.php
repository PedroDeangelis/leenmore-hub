<?php

use App\Livewire\Worker\ActivityReport;
use App\Livewire\Worker\Home;
use App\Livewire\Worker\ProjectShow;
use App\Livewire\Worker\ReceiptCreate;
use App\Livewire\Worker\ReceiptHistory;
use App\Livewire\Worker\ResourceIndex;
use App\Livewire\Worker\ResourceShow;
use Illuminate\Support\Facades\Route;

/*
 * Worker portal — `worker` role only. The field-activist mobile app: their
 * projects, the shareholders assigned to them, and the activity report they
 * file per shareholder.
 */
Route::middleware(['auth', 'role:worker'])->prefix('app')->name('worker.')->group(function () {
    Route::livewire('/', Home::class)->name('dashboard');

    // Receipt submission (영수증 제출) + the worker's own history (영수증 내역 보기).
    Route::livewire('receipts/create', ReceiptCreate::class)->name('receipts.create');
    Route::livewire('receipts', ReceiptHistory::class)->name('receipts.index');

    // Project resources (프로젝트 자료실): assigned projects with resources, then one project's links/files.
    Route::livewire('resources', ResourceIndex::class)->name('resources.index');
    Route::livewire('resources/{project}', ResourceShow::class)->name('resources.show');

    Route::livewire('projects/{project}', ProjectShow::class)->name('projects.show');
    Route::livewire('projects/{project}/shareholders/{projectShareholder}/report', ActivityReport::class)
        ->name('projects.shareholders.report');
});
