<?php

namespace App\Http\Controllers;

use App\Enums\RosterSortColumn;
use App\Models\Project;
use App\Models\User;
use App\Services\WorkerRoster\RosterReportGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads one worker's 명부양식 (roster form) for a project — the compose
 * page's "명부양식 내려받기" button. `?type=xlsx|pdf` picks the format and
 * `?sort=<column>&desc=1` mirror the page's sort controls, so the file matches
 * what the email attachment would contain. Gated by `can:send-worker-emails`.
 */
class ProjectWorkerRosterExportController extends Controller
{
    public function __invoke(Request $request, Project $project, User $user, RosterReportGenerator $generator): BinaryFileResponse
    {
        abort_unless($project->assignedWorkers()->whereKey($user->id)->exists(), 404);

        $type = $request->query('type', 'xlsx');
        abort_unless(in_array($type, ['xlsx', 'pdf'], true), 404);

        $files = $generator->generateForWorker(
            $project,
            $user,
            RosterSortColumn::tryFrom((string) $request->query('sort')) ?? RosterSortColumn::No,
            $request->boolean('desc'),
            withPdf: $type === 'pdf',
        );

        // No roster rows for this worker — nothing to download.
        abort_if($files === null, 404);

        $path = $type === 'pdf' ? $files->pdfPath : $files->xlsxPath;

        return response()->download(
            Storage::disk('local')->path($path),
            $files->filenameStem.'.'.$type,
        );
    }
}
