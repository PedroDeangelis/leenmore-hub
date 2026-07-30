<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

/*
 * The read-only REST API. One bearer token (minted on the dashboard's API tab)
 * authenticates every call via the `api.token` middleware; there is no user
 * session here. Everything returned is masked by App\Support\ApiData, and the
 * endpoints mirror the MCP tools — same data, plain HTTP instead of MCP.
 *
 * Versioned under /api/v1 so the shape can evolve without breaking a client that
 * already holds a token. The endpoint list lives in App\Support\ApiCatalog.
 */
Route::middleware(['api.token', 'throttle:api'])
    ->prefix('v1')
    ->group(function (): void {
        Route::get('projects', [ApiController::class, 'projects']);
        Route::get('projects/{project}', [ApiController::class, 'project'])->whereNumber('project');
        Route::get('projects/{project}/shareholders', [ApiController::class, 'shareholders'])->whereNumber('project');
        Route::get('projects/{project}/activity-data', [ApiController::class, 'activityData'])->whereNumber('project');
        Route::get('submissions', [ApiController::class, 'submissions']);
        Route::get('receipts', [ApiController::class, 'receipts']);
        Route::get('users', [ApiController::class, 'users']);
    });
