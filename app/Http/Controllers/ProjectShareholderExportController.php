<?php

namespace App\Http\Controllers;

use App\Exports\ShareholderExporter;
use App\Imports\ShareholderImporter;
use App\Models\Project;
use App\Models\ProjectShareholder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a project's current shareholder roster as a CSV in the firm's import
 * layout — the same columns {@see ShareholderImporter} reads — so
 * the file is a faithful, re-importable snapshot of the live data. Gated by
 * `can:manage-shareholders`; rows are chunked so a large roster never has to be
 * held in memory at once.
 */
class ProjectShareholderExportController extends Controller
{
    public function __invoke(Project $project, ShareholderExporter $exporter): StreamedResponse
    {
        $slug = Str::slug($project->title) ?: 'project-'.$project->id;

        return response()->streamDownload(function () use ($project, $exporter): void {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens the Korean headers correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $exporter->headers());

            $project->shareholders()
                ->with(['shareholder', 'workers'])
                ->chunk(500, function (Collection $assignments) use ($out, $exporter): void {
                    foreach ($assignments as $assignment) {
                        /** @var ProjectShareholder $assignment */
                        fputcsv($out, $exporter->row($assignment));
                    }
                });

            fclose($out);
        }, "shareholders-{$slug}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
