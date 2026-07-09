<?php

namespace App\Services\WorkerRoster;

use App\Enums\RosterSortColumn;
use App\Exports\WorkerRosterExporter;
use App\Models\Project;
use App\Models\User;
use App\Support\WorksheetName;
use Illuminate\Support\Facades\Storage;

/**
 * Generates 명부양식 (worker roster form) files on the private `local` disk
 * under worker-rosters/{project}/ — single-worker XLSX(+PDF) for downloads and
 * email attachments, and the multi-sheet 전체 다운로드 bundle. The Laravel port
 * of the retired leenmore-storage `/generate-worker-report` endpoints.
 */
class RosterReportGenerator
{
    private readonly RosterTemplateWorkbook $workbook;

    private readonly RosterPdfRenderer $pdf;

    public function __construct(?RosterTemplateWorkbook $workbook = null, ?RosterPdfRenderer $pdf = null)
    {
        $this->workbook = $workbook ?? new RosterTemplateWorkbook(resource_path('templates/email-to-worker.xlsx'));
        $this->pdf = $pdf ?? new RosterPdfRenderer;
    }

    /**
     * Generate one worker's roster XLSX (and PDF when asked). Returns null when
     * the worker has no roster rows on the project — legacy behaviour: no rows,
     * no 명부양식.
     */
    public function generateForWorker(
        Project $project,
        User $worker,
        RosterSortColumn $sortColumn = RosterSortColumn::No,
        bool $sortDesc = false,
        bool $withPdf = false,
    ): ?RosterFiles {
        $rows = WorkerRosterExporter::for($project, $worker, $sortColumn, $sortDesc)->rows();
        if ($rows === []) {
            return null;
        }

        $stem = $this->filenameStem($project->title.'_주주명부_'.$worker->name);
        // Storage names carry the worker id so same-named workers can never
        // overwrite (and thus receive) each other's roster.
        $directory = $this->directory($project);
        $xlsxPath = $directory.'/'.$stem.'-w'.$worker->id.'.xlsx';

        $disk = Storage::disk('local');
        $disk->makeDirectory($directory);

        $this->workbook->writeSingle($disk->path($xlsxPath), $rows, $project->title, $worker->name);

        $pdfPath = null;
        if ($withPdf) {
            $pdfPath = $directory.'/'.$stem.'-w'.$worker->id.'.pdf';
            $this->pdf->render($disk->path($xlsxPath), $disk->path($pdfPath), $project->title, $worker->name, $rows);
        }

        return new RosterFiles($xlsxPath, $pdfPath, $stem);
    }

    /**
     * Generate the 명부양식 전체 다운로드 bundle: one sheet per worker (workers
     * without roster rows are skipped, like the legacy multi report). Returns
     * null when no worker has any rows.
     *
     * @param  iterable<int, User>  $workers
     */
    public function generateBundle(
        Project $project,
        iterable $workers,
        RosterSortColumn $sortColumn = RosterSortColumn::No,
        bool $sortDesc = false,
    ): ?RosterFiles {
        $sheets = [];
        $usedTitles = [];

        foreach ($workers as $worker) {
            $rows = WorkerRosterExporter::for($project, $worker, $sortColumn, $sortDesc)->rows();
            if ($rows === []) {
                continue;
            }

            $sheets[] = [
                'title' => $this->uniqueSheetTitle($worker->name, $usedTitles),
                'workerName' => $worker->name,
                'rows' => $rows,
            ];
        }

        if ($sheets === []) {
            return null;
        }

        // Legacy named this "{title}_????_multi-worker" (mojibake); restore the intent.
        $stem = $this->filenameStem($project->title.'_주주명부_전체');
        $directory = $this->directory($project);
        $xlsxPath = $directory.'/'.$stem.'.xlsx';

        $disk = Storage::disk('local');
        $disk->makeDirectory($directory);

        $this->workbook->writeBundle($disk->path($xlsxPath), $sheets, $project->title);

        return new RosterFiles($xlsxPath, null, $stem);
    }

    private function directory(Project $project): string
    {
        return 'worker-rosters/'.$project->id;
    }

    /**
     * A path/OS-safe filename stem that keeps Unicode (Korean) letters —
     * legacy `sanitizeFilenameStem`.
     */
    private function filenameStem(string $filename): string
    {
        $stem = pathinfo(basename(str_replace('\\', '/', trim($filename))), PATHINFO_FILENAME);

        $sanitized = preg_replace('/[^\p{L}\p{N}._\-\s]/u', '-', $stem) ?? '';
        $sanitized = preg_replace('/\s+/u', '-', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/-+/u', '-', $sanitized) ?? $sanitized;
        $sanitized = trim($sanitized, '-. ');

        return $sanitized !== '' ? $sanitized : 'worker-roster';
    }

    /**
     * An Excel-safe worksheet title, deduplicated across the bundle's sheets.
     *
     * @param  array<string, true>  $usedTitles
     */
    private function uniqueSheetTitle(string $workerName, array &$usedTitles): string
    {
        $base = WorksheetName::for($workerName, 'Worker');

        $title = $base;
        $counter = 1;
        while (isset($usedTitles[mb_strtolower($title, 'UTF-8')])) {
            $counter++;
            $suffix = '-'.$counter;
            $title = mb_substr($base, 0, 31 - strlen($suffix), 'UTF-8').$suffix;
        }

        $usedTitles[mb_strtolower($title, 'UTF-8')] = true;

        return $title;
    }
}
