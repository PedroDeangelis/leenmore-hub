<?php

namespace App\Http\Controllers;

use App\Enums\RosterSortColumn;
use App\Models\Project;
use App\Services\WorkerRoster\RosterReportGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads the 명부양식 전체 다운로드 workbook: one sheet per assigned worker
 * (workers without roster rows are skipped). `?sort=<column>&desc=1` mirror
 * the compose page's sort controls. Gated by `can:send-worker-emails`.
 */
class ProjectMultiWorkerRosterExportController extends Controller
{
    public function __invoke(Request $request, Project $project, RosterReportGenerator $generator): BinaryFileResponse
    {
        $files = $generator->generateBundle(
            $project,
            $project->assignedWorkers()->get(),
            RosterSortColumn::tryFrom((string) $request->query('sort')) ?? RosterSortColumn::No,
            $request->boolean('desc'),
        );

        // No worker has any roster rows — nothing to download.
        abort_if($files === null, 404);

        return response()->download(
            Storage::disk('local')->path($files->xlsxPath),
            $files->filenameStem.'.xlsx',
        );
    }
}
