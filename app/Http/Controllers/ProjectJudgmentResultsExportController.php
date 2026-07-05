<?php

namespace App\Http\Controllers;

use App\Enums\ResultColor;
use App\Models\Project;
use App\Support\JudgmentTally;
use App\Support\WorksheetName;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderStyle;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads a project's 판단 결과 현황 as a one-sheet Excel report — the Laravel
 * port of the legacy React app's `downloadExcelNotes`: a heading block (title,
 * report subtitle, written/meeting/days-left dates, issued vs target shares)
 * above the colour-banded judgment table the dashboard shows. Unlike the legacy
 * file everything is computed here from {@see JudgmentTally} — no embedded
 * spreadsheet formulas pointing at hard-coded cells — so the workbook always
 * matches the on-screen tally. Gated by `can:export-data`.
 */
class ProjectJudgmentResultsExportController extends Controller
{
    private const SUBTITLE = '위임 권유 활동 현황 보고';

    /** The table header, matching the dashboard panel's columns. */
    private const TABLE_HEADERS = ['판단', '주식수', '각 판단별 진행 현황(%)', '총 합(주식수)', '총 합(%)'];

    private const DATE_FORMAT = 'yyyy-mm-dd';

    private const SHARES_FORMAT = '#,##0';

    private const PERCENT_FORMAT = '0.00%';

    /** Row number of the first judgment row (after the 11-row heading block). */
    private const FIRST_TABLE_ROW = 12;

    public function __invoke(Project $project): BinaryFileResponse
    {
        $project->load('results');
        $tally = JudgmentTally::for($project);

        $options = new Options;
        $options->setColumnWidth(30, 1);
        $options->setColumnWidth(18, 2);
        $options->setColumnWidth(25, 3);
        $options->setColumnWidth(18, 4);
        $options->setColumnWidth(25, 5);

        // Title and subtitle each span the full table width (A1:E1, A2:E2).
        $options->mergeCells(0, 1, 4, 1);
        $options->mergeCells(0, 2, 4, 2);
        $this->mergeGroupTotals($options, $tally);

        // XLSX is a zip, so it must be fully assembled before download; build it
        // in a temp file and remove it once sent.
        $path = tempnam(sys_get_temp_dir(), 'judgment-results-');

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(WorksheetName::for($project->title));

        $this->addHeading($writer, $project);
        $this->addTable($writer, $tally);

        $writer->close();

        $slug = Str::slug($project->title) ?: 'project-'.$project->id;

        return response()
            ->download($path, "judgment-results-{$slug}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Merge each colour group's total cells (columns D and E) down the group's
     * rows — the spreadsheet version of the dashboard table's rowspan.
     */
    private function mergeGroupTotals(Options $options, JudgmentTally $tally): void
    {
        $row = self::FIRST_TABLE_ROW;

        foreach ($tally->groups as $group) {
            $count = count($group['rows']);

            if ($count > 1) {
                $options->mergeCells(3, $row, 3, $row + $count - 1);
                $options->mergeCells(4, $row, 4, $row + $count - 1);
            }

            $row += $count;
        }
    }

    /**
     * Rows 1-10: title, subtitle, the written/meeting/days-left dates and the
     * issued vs target share counts.
     */
    private function addHeading(Writer $writer, Project $project): void
    {
        $heading = (new Style)->withFontBold(true)->withFontSize(16)
            ->withCellAlignment(CellAlignment::CENTER)
            ->withCellVerticalAlignment(CellVerticalAlignment::CENTER);

        $writer->addRow(Row::fromValuesWithStyle([(string) $project->title], $heading, height: 32));
        $writer->addRow(Row::fromValuesWithStyle([self::SUBTITLE], $heading));
        $writer->addRow(Row::fromValues([]));

        $bold = (new Style)->withFontBold(true);
        $date = $bold->withFormat(self::DATE_FORMAT);
        $countdown = $project->meetingCountdown();

        // Days left in red once the meeting has passed (the legacy file did this
        // with conditional formatting; the value is computed, so style directly).
        $daysLeft = $bold;

        if ($countdown?->hasPassed()) {
            $daysLeft = $daysLeft->withFontColor('FF0000');
        }

        $this->addMetaRow($writer, '작성 일:', Cell::fromValue(now(), $date));
        $this->addMetaRow($writer, '주주총회 일:', Cell::fromValue($project->end_date, $date));
        $this->addMetaRow($writer, '잔여 일:', Cell::fromValue($countdown?->daysLeft, $daysLeft));

        $writer->addRow(Row::fromValues([]));

        $label = $bold->withCellAlignment(CellAlignment::CENTER);
        $shares = (new Style)->withFormat(self::SHARES_FORMAT);

        $writer->addRow(new Row([
            Cell::fromValue('총 발행 주식 수', $label),
            4 => Cell::fromValue('목표 주식 수', $label),
        ]));
        $writer->addRow(new Row([
            Cell::fromValue($project->shares_issued, $shares),
            4 => Cell::fromValue($project->shares_target, $shares),
        ]));
        $writer->addRow(Row::fromValues([]));
    }

    /**
     * A heading meta row: bold right-aligned label in column D, value in E.
     */
    private function addMetaRow(Writer $writer, string $label, Cell $value): void
    {
        $labelStyle = (new Style)->withFontBold(true)->withCellAlignment(CellAlignment::RIGHT);

        $writer->addRow(new Row([3 => Cell::fromValue($label, $labelStyle), 4 => $value]));
    }

    /**
     * Rows 11+: the bordered header row, then one colour-filled row per judged
     * 판단 with the group totals on each group's first row.
     */
    private function addTable(Writer $writer, JudgmentTally $tally): void
    {
        $writer->addRow($this->tableHeaderRow());

        $groups = $tally->groups;
        $lastGroup = array_key_last($groups);

        foreach ($groups as $gi => $group) {
            foreach ($group['rows'] as $i => $row) {
                $lastTableRow = $gi === $lastGroup && $i === array_key_last($group['rows']);

                $writer->addRow($this->resultRow($group, $row, $i === 0, $lastTableRow));
            }
        }
    }

    private function tableHeaderRow(): Row
    {
        $cells = [];
        $lastColumn = array_key_last(self::TABLE_HEADERS);

        foreach (self::TABLE_HEADERS as $column => $header) {
            $style = (new Style)->withFontBold(true)
                ->withCellAlignment(CellAlignment::CENTER)
                ->withCellVerticalAlignment(CellVerticalAlignment::CENTER)
                ->withBorder(new Border(
                    new BorderPart(BorderName::TOP, width: BorderWidth::MEDIUM),
                    new BorderPart(BorderName::BOTTOM, style: BorderStyle::DOUBLE),
                    new BorderPart(BorderName::RIGHT, width: $column === $lastColumn ? BorderWidth::MEDIUM : BorderWidth::THIN),
                ));

            $cells[] = Cell::fromValue($header, $style);
        }

        return new Row($cells, height: 20);
    }

    /**
     * One judgment row: name / shares / percent on the band tint, plus the
     * group-total cells on the stronger tint. The totals only carry values on
     * the group's first row — the rest sit hidden under the vertical merge but
     * keep the fill so the band reads correctly even unmerged.
     *
     * @param  array{color: ResultColor, rows: array, totalShares: int, totalPercent: ?float}  $group
     * @param  array{name: string, color: ResultColor, shares: int, percent: ?float}  $row
     */
    private function resultRow(array $group, array $row, bool $firstOfGroup, bool $lastTableRow): Row
    {
        $band = $this->tableCellStyle($row['color']->bandHex(), $lastTableRow);
        $total = $this->tableCellStyle($group['color']->totalHex(), $lastTableRow, lastColumn: true)
            ->withFontBold(true)
            ->withCellVerticalAlignment(CellVerticalAlignment::CENTER);

        return new Row([
            Cell::fromValue($row['name'], $band),
            Cell::fromValue($row['shares'], $band->withFormat(self::SHARES_FORMAT)),
            Cell::fromValue($this->fraction($row['percent']), $band->withFormat(self::PERCENT_FORMAT)),
            Cell::fromValue($firstOfGroup ? $group['totalShares'] : null, $total->withFormat(self::SHARES_FORMAT)),
            Cell::fromValue($firstOfGroup ? $this->fraction($group['totalPercent']) : null, $total->withFormat(self::PERCENT_FORMAT)),
        ]);
    }

    /**
     * Base style for a judgment table cell: colour fill plus the table frame
     * (thin rules inside, medium on the outer edge).
     */
    private function tableCellStyle(string $fillHex, bool $lastTableRow, bool $lastColumn = false): Style
    {
        return (new Style)
            ->withBackgroundColor(strtoupper(ltrim($fillHex, '#')))
            ->withBorder(new Border(
                new BorderPart(BorderName::BOTTOM, width: $lastTableRow ? BorderWidth::MEDIUM : BorderWidth::THIN),
                new BorderPart(BorderName::RIGHT, width: $lastColumn ? BorderWidth::MEDIUM : BorderWidth::THIN),
            ));
    }

    /**
     * A 0-100 percentage as the 0-1 fraction Excel's percent format expects,
     * or null (empty cell) when there is no target to measure against.
     */
    private function fraction(?float $percent): ?float
    {
        return $percent === null ? null : $percent / 100;
    }
}
