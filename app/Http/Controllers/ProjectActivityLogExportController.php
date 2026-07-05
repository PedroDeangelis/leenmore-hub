<?php

namespace App\Http\Controllers;

use App\Exports\ActivityLogExporter;
use App\Models\EsignonShareholder;
use App\Models\Project;
use App\Support\WorksheetName;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams a project's 활동내역(엑셀) — one sheet, one row per shareholder, with a
 * per-day block of report columns. The column layout and per-day aggregation
 * live in {@see ActivityLogExporter} (the port of the legacy `getDownloadCSV`);
 * here we just write it out as a real .xlsx. When the project has ESIGNON
 * signers who never matched a roster entry, a second "주주명부 누락" sheet lists
 * them. Gated by `can:export-data`.
 */
class ProjectActivityLogExportController extends Controller
{
    /** The "주주명부 누락" sheet columns (legacy MISSING_SHAREHOLDERS_HEADERS). */
    private const MISSING_HEADERS = ['이름', '생년월일', '전자위임시간', '전자위임연락처'];

    public function __invoke(Project $project): BinaryFileResponse
    {
        $exporter = ActivityLogExporter::for($project);
        $boldHeader = (new Style)->withFontBold(true);

        // XLSX is a zip, so it must be fully assembled before download; build it
        // in a temp file and remove it once sent.
        $path = tempnam(sys_get_temp_dir(), 'activity-log-');

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(WorksheetName::for($project->title));

        $writer->addRow(Row::fromValuesWithStyle($exporter->headers(), $boldHeader));

        foreach ($exporter->rows() as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $this->addMissingShareholdersSheet($writer, $project, $boldHeader);

        $writer->close();

        $slug = Str::slug($project->title) ?: 'project-'.$project->id;

        return response()
            ->download($path, "activity-log-{$slug}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Append the "주주명부 누락" sheet: ESIGNON signers for this project who never
     * matched a roster entry (still flagged `needs_sync`). Skipped when there are
     * none.
     */
    private function addMissingShareholdersSheet(Writer $writer, Project $project, Style $boldHeader): void
    {
        $missing = EsignonShareholder::query()
            ->where('project_id', $project->id)
            ->where('needs_sync', true)
            ->orderBy('name')
            ->get();

        if ($missing->isEmpty()) {
            return;
        }

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('주주명부 누락');

        $writer->addRow(Row::fromValuesWithStyle(self::MISSING_HEADERS, $boldHeader));

        foreach ($missing as $signer) {
            $writer->addRow(Row::fromValues([
                $signer->name,
                $signer->identifier ?? '',
                $signer->completed_date?->format('Y-m-d H:i:s') ?? '',
                $signer->contact ?? '',
            ]));
        }
    }
}
