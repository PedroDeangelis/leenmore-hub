<?php

use App\Http\Controllers\ProjectActivityDataExportController;
use App\Http\Controllers\ProjectActivityLogExportController;
use App\Http\Controllers\ProjectJudgmentResultsExportController;
use App\Http\Controllers\ProjectMultiWorkerRosterExportController;
use App\Http\Controllers\ProjectShareholderExportController;
use App\Http\Controllers\ProjectWorkerRosterExportController;
use App\Http\Controllers\ReportFileController;
use App\Http\Controllers\ShareholderTemplateController;
use App\Livewire\Activity\Index as ActivityIndex;
use App\Livewire\Activity\Report as ActivityReport;
use App\Livewire\Activity\Roster as ActivityRoster;
use App\Livewire\ActivityData\Index as ActivityDataIndex;
use App\Livewire\ActivityData\Show as ActivityDataShow;
use App\Livewire\Admin\Options as AdminOptions;
use App\Livewire\EmailToWorker\Compose as EmailToWorkerCompose;
use App\Livewire\EmailToWorker\History as EmailToWorkerHistory;
use App\Livewire\EmailToWorker\Index as EmailToWorkerIndex;
use App\Livewire\Projects\Create as ProjectsCreate;
use App\Livewire\Projects\Index as ProjectsIndex;
use App\Livewire\Projects\Show as ProjectsShow;
use App\Livewire\Receipts\Edit as ReceiptsEdit;
use App\Livewire\Receipts\Index as ReceiptsIndex;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Reports\Show as ReportsShow;
use App\Livewire\Resources\Index as ResourcesIndex;
use App\Livewire\Resources\Manage as ResourcesManage;
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

    // Export a project's current roster as a re-importable CSV.
    Route::get('projects/{project}/shareholders/export', ProjectShareholderExportController::class)
        ->whereNumber('project')
        ->middleware('can:manage-shareholders')
        ->name('projects.shareholders.export');

    // Export a project's activity log (per-day report history) as an Excel workbook.
    Route::get('projects/{project}/activity-log/export', ProjectActivityLogExportController::class)
        ->whereNumber('project')
        ->middleware('can:export-data')
        ->name('projects.activity-log.export');

    // Export a project's 판단 결과 현황 (judgment results summary) as an Excel report.
    Route::get('projects/{project}/judgment-results/export', ProjectJudgmentResultsExportController::class)
        ->whereNumber('project')
        ->middleware('can:export-data')
        ->name('projects.judgment-results.export');

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

    // 활동 데이터: per-project worker-productivity analytics (contact/proxy
    // stats per worker and per day) with the formula-driven Excel export.
    Route::livewire('activity-data', ActivityDataIndex::class)
        ->middleware('can:view-activity-data')
        ->name('activity-data.index');

    Route::livewire('activity-data/{project}', ActivityDataShow::class)
        ->whereNumber('project')
        ->middleware('can:view-activity-data')
        ->name('activity-data.show');

    Route::get('projects/{project}/activity-data/export', ProjectActivityDataExportController::class)
        ->whereNumber('project')
        ->middleware('can:view-activity-data')
        ->name('activity-data.export');

    // Receipts archive: admin + office may read worker-submitted receipts.
    Route::livewire('receipts', ReceiptsIndex::class)
        ->middleware('can:view-receipts')
        ->name('receipts.index');

    Route::livewire('receipts/{receipt}/edit', ReceiptsEdit::class)
        ->middleware('can:manage-receipts')
        ->name('receipts.edit');

    // Project resources (프로젝트 자료실): list all projects, then manage one's links/files.
    Route::livewire('resources', ResourcesIndex::class)
        ->middleware('can:manage-resources')
        ->name('resources.index');

    Route::livewire('resources/{project}', ResourcesManage::class)
        ->middleware('can:manage-resources')
        ->name('resources.manage');

    // Email to worker (활동가에게 이메일 보내기): admin-only. Pick a project, compose
    // an email to its workers (with resource links/files and 명부양식 attachments),
    // and monitor per-recipient delivery. `history` is declared before
    // `{project}` so it is not captured as a project id.
    Route::livewire('email-to-worker', EmailToWorkerIndex::class)
        ->middleware('can:send-worker-emails')
        ->name('email-to-worker.index');

    Route::livewire('email-to-worker/history', EmailToWorkerHistory::class)
        ->middleware('can:send-worker-emails')
        ->name('email-to-worker.history');

    Route::livewire('email-to-worker/{project}', EmailToWorkerCompose::class)
        ->whereNumber('project')
        ->middleware('can:send-worker-emails')
        ->name('email-to-worker.compose');

    // 명부양식 downloads: the whole-project bundle (one sheet per worker) and one
    // worker's XLSX/PDF. Sort options come from the compose page's controls.
    Route::get('projects/{project}/worker-roster', ProjectMultiWorkerRosterExportController::class)
        ->whereNumber('project')
        ->middleware('can:send-worker-emails')
        ->name('email-to-worker.roster.all');

    Route::get('projects/{project}/worker-roster/{user}', ProjectWorkerRosterExportController::class)
        ->whereNumber('project')
        ->whereNumber('user')
        ->middleware('can:send-worker-emails')
        ->name('email-to-worker.roster');

    // Options: admin-only app-wide settings (announcement, receipt categories).
    Route::livewire('options', AdminOptions::class)
        ->middleware('can:manage-settings')
        ->name('options.edit');
});
