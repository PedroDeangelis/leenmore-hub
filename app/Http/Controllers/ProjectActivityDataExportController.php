<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\ActivityData;
use App\Support\WorksheetName;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
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
 * Streams the 활동 데이터 workbook — the Laravel port of the legacy
 * `downloadExcelActivityData.js`. One row per worker; fixed columns A–P
 * (targets, live per-day averages, cumulative 누계, delegation rates as real
 * spreadsheet formulas) plus a 4-column block per activity day. Totals row in
 * blue, averages row in red, colour-coded column groups, thick-boxed blocks.
 * The numbers come from {@see ActivityData}, so the workbook always matches
 * the analytics page. Gated by `can:view-activity-data`.
 *
 * Deliberate fixes vs legacy: the G–J / O–P average cells use real
 * `AVERAGE()` formulas (legacy divided an empty totals cell and always showed
 * 0), and the download filename follows the hub's export convention.
 */
class ProjectActivityDataExportController extends Controller
{
    /** Row-2 sub-labels of every 4-column metric block (일 평균, 누계, per-day). */
    private const METRIC_LABELS = ['접촉 횟수', '접촉 주식수', '위임 주주수', '위임 주식수'];

    /** First day block starts at column Q. */
    private const FIRST_DAY_COLUMN = 17;

    /** Alternating day-block fills: even day index green, odd yellow (legacy palette). */
    private const DAY_FILLS = ['D9EAD3', 'FFF2CC'];

    private const GROUP_GRAY = '808080';

    private const GROUP_LIGHT_GRAY = 'D0CECE';

    private const GROUP_PALE_BLUE = 'D6DCE4';

    private const FONT_BLUE = '0000FF';

    private const FONT_RED = 'FF0000';

    /** @var array<string, Style> memoized styles, keyed by their traits */
    private array $styles = [];

    public function __invoke(Request $request, Project $project): BinaryFileResponse
    {
        $days = collect((array) $request->query('days', []))
            ->filter(fn ($day): bool => is_string($day) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1)
            ->values()
            ->all();

        $project->load('results');
        $data = ActivityData::for($project, $days);

        // Nothing filed (or the filter excluded everything) — nothing to export.
        abort_if($data->isEmpty(), 404);

        $workers = $data->workers();
        $exportDays = $data->days();
        $workerCount = count($workers);
        $totalsRow = $workerCount + 3;
        $averagesRow = $workerCount + 4;
        $lastColumn = self::FIRST_DAY_COLUMN + count($exportDays) * 4 - 1;

        $options = new Options;
        $this->configureLayout($options, $workerCount, count($exportDays), $lastColumn);

        $path = tempnam(sys_get_temp_dir(), 'activity-data-');

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(WorksheetName::for('활동 데이터'));

        $writer->addRow(new Row($this->headerRowOne($data, $workerCount, $lastColumn, $averagesRow)));
        $writer->addRow(new Row($this->headerRowTwo($data, $lastColumn, $averagesRow)));

        foreach ($workers as $index => $worker) {
            $writer->addRow(new Row($this->workerRow($data, $worker, $index, $workerCount, $lastColumn, $averagesRow)));
        }

        $writer->addRow(new Row($this->totalsRow($data, $workerCount, $lastColumn, $averagesRow)));
        $writer->addRow(new Row($this->averagesRow($data, $workerCount, $lastColumn, $averagesRow)));

        $writer->close();

        $slug = Str::slug($project->title) ?: 'project-'.$project->id;

        return response()
            ->download($path, "activity-data-{$slug}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Column widths, default font, and every merged range (0-based columns,
     * 1-based rows).
     */
    private function configureLayout(Options $options, int $workerCount, int $dayCount, int $lastColumn): void
    {
        // Options' fallback style is already the legacy workbook font (Calibri 12).
        $options->setColumnWidth(14, 1);   // A labels
        $options->setColumnWidth(16, 2);   // B worker names
        $options->setColumnWidth(4, 3);    // C spacer
        for ($column = 4; $column <= 16; $column++) {
            $options->setColumnWidth(13, $column);
        }
        for ($column = self::FIRST_DAY_COLUMN; $column <= $lastColumn; $column++) {
            $options->setColumnWidth(11, $column);
        }

        $options->mergeCells(3, 1, 3, 2);    // D1:D2 활동일수
        $options->mergeCells(4, 1, 5, 1);    // E1:F1 Target
        $options->mergeCells(6, 1, 9, 1);    // G1:J1 일 평균
        $options->mergeCells(10, 1, 13, 1);  // K1:N1 누 계
        $options->mergeCells(14, 1, 15, 1);  // O1:P1 담당 대비 위임률
        $options->mergeCells(0, 3, 0, $workerCount + 2); // A3:A{N+2} 활동가 목록

        for ($day = 0; $day < $dayCount; $day++) {
            $start = self::FIRST_DAY_COLUMN - 1 + $day * 4;
            $options->mergeCells($start, 1, $start + 3, 1);
        }
    }

    /**
     * @return list<Cell>
     */
    private function headerRowOne(ActivityData $data, int $workerCount, int $lastColumn, int $bottomRow): array
    {
        $cells = array_fill(1, $lastColumn, null);

        $cells[1] = Cell::fromValue('총 활동가:');
        $cells[2] = new FormulaCell('=COUNTA(B3:B'.($workerCount + 2).')');
        $cells[4] = Cell::fromValue('활동일수', $this->blockStyle(4, 4, 4, 1, $bottomRow, 1, center: true));
        $cells[5] = Cell::fromValue('Target', $this->blockStyle(5, 5, 6, 1, $bottomRow, 1, center: true));
        $cells[6] = Cell::fromValue('', $this->blockStyle(6, 5, 6, 1, $bottomRow, 1));
        $cells[7] = Cell::fromValue('일 평균', $this->blockStyle(7, 7, 10, 1, $bottomRow, 1, fill: self::GROUP_GRAY, fontColor: 'FFFFFF', center: true));
        foreach ([8, 9, 10] as $column) {
            $cells[$column] = Cell::fromValue('', $this->blockStyle($column, 7, 10, 1, $bottomRow, 1, fill: self::GROUP_GRAY));
        }
        $cells[11] = Cell::fromValue('누 계', $this->blockStyle(11, 11, 14, 1, $bottomRow, 1, fill: self::GROUP_LIGHT_GRAY, center: true));
        foreach ([12, 13, 14] as $column) {
            $cells[$column] = Cell::fromValue('', $this->blockStyle($column, 11, 14, 1, $bottomRow, 1));
        }
        $cells[15] = Cell::fromValue('담당 대비 위임률', $this->blockStyle(15, 15, 16, 1, $bottomRow, 1, fill: self::GROUP_PALE_BLUE, center: true));
        $cells[16] = Cell::fromValue('', $this->blockStyle(16, 15, 16, 1, $bottomRow, 1));

        foreach ($data->days() as $index => $day) {
            $start = self::FIRST_DAY_COLUMN + $index * 4;
            $fill = self::DAY_FILLS[$index % 2];

            $cells[$start] = Cell::fromValue(
                Carbon::parse($day)->format('m월 d일'),
                $this->blockStyle($start, $start, $start + 3, 1, $bottomRow, 1, fill: $fill, center: true),
            );
            for ($offset = 1; $offset <= 3; $offset++) {
                $cells[$start + $offset] = Cell::fromValue('', $this->blockStyle($start + $offset, $start, $start + 3, 1, $bottomRow, 1, fill: $fill));
            }
        }

        return $this->fillEmpty($cells);
    }

    /**
     * @return list<Cell>
     */
    private function headerRowTwo(ActivityData $data, int $lastColumn, int $bottomRow): array
    {
        $cells = array_fill(1, $lastColumn, null);

        $cells[1] = Cell::fromValue('총 활동일수:');
        $cells[2] = Cell::fromValue(count($data->days()));
        $cells[4] = Cell::fromValue('', $this->blockStyle(4, 4, 4, 2, $bottomRow, 2));
        $cells[5] = Cell::fromValue('주주 수', $this->blockStyle(5, 5, 6, 2, $bottomRow, 2, center: true));
        $cells[6] = Cell::fromValue('주식 수', $this->blockStyle(6, 5, 6, 2, $bottomRow, 2, center: true));

        foreach (self::METRIC_LABELS as $offset => $label) {
            $cells[7 + $offset] = Cell::fromValue($label, $this->blockStyle(7 + $offset, 7, 10, 2, $bottomRow, 2, fill: self::GROUP_GRAY, fontColor: 'FFFFFF', center: true));
            $cells[11 + $offset] = Cell::fromValue($label, $this->blockStyle(11 + $offset, 11, 14, 2, $bottomRow, 2, fill: self::GROUP_LIGHT_GRAY, center: true));
        }

        $cells[15] = Cell::fromValue('담당주주수 대비 위임률(%)', $this->blockStyle(15, 15, 16, 2, $bottomRow, 2, fill: self::GROUP_PALE_BLUE, center: true));
        $cells[16] = Cell::fromValue('담당주식수 대비 위임률(%)', $this->blockStyle(16, 15, 16, 2, $bottomRow, 2, fill: self::GROUP_PALE_BLUE, center: true));

        foreach ($data->days() as $index => $day) {
            $start = self::FIRST_DAY_COLUMN + $index * 4;
            $fill = self::DAY_FILLS[$index % 2];

            foreach (self::METRIC_LABELS as $offset => $label) {
                $cells[$start + $offset] = Cell::fromValue($label, $this->blockStyle($start + $offset, $start, $start + 3, 2, $bottomRow, 2, fill: $fill, center: true));
            }
        }

        return $this->fillEmpty($cells);
    }

    /**
     * @param  array{key: string, name: string, daysWorked: int, shareholderCount: int, assignedShares: int}  $worker
     * @return list<Cell>
     */
    private function workerRow(ActivityData $data, array $worker, int $index, int $workerCount, int $lastColumn, int $bottomRow): array
    {
        $row = $index + 3;
        $cells = array_fill(1, $lastColumn, null);

        // The vertical 활동가 목록 label lives in the merged A3:A{N+2} range.
        if ($index === 0) {
            $cells[1] = Cell::fromValue('활동가 목록', $this->style(center: true));
        }

        $cells[2] = Cell::fromValue($worker['name'], $this->style(center: true));
        $cells[4] = Cell::fromValue($worker['daysWorked'], $this->blockStyle(4, 4, 4, $row, $bottomRow, $row));
        $cells[5] = Cell::fromValue($worker['shareholderCount'], $this->blockStyle(5, 5, 6, $row, $bottomRow, $row));
        $cells[6] = Cell::fromValue($worker['assignedShares'], $this->blockStyle(6, 5, 6, $row, $bottomRow, $row));

        // 일 평균 G–J = 누계 ÷ 활동일수, as live formulas (G=K/D … J=N/D).
        foreach (['K', 'L', 'M', 'N'] as $offset => $cumulative) {
            $column = 7 + $offset;
            $cells[$column] = new FormulaCell(
                '='.$cumulative.$row.'/D'.$row,
                null,
                $this->blockStyle($column, 7, 10, $row, $bottomRow, $row),
            );
        }

        // 누계 K–N = the sum of every day block's matching column.
        foreach ([0, 1, 2, 3] as $offset) {
            $column = 11 + $offset;
            $references = [];
            foreach (array_keys($data->days()) as $dayIndex) {
                $references[] = $this->columnLetter(self::FIRST_DAY_COLUMN + $dayIndex * 4 + $offset).$row;
            }
            $cells[$column] = new FormulaCell(
                '=SUM('.implode(',', $references).')',
                null,
                $this->blockStyle($column, 11, 14, $row, $bottomRow, $row),
            );
        }

        // 담당 대비 위임률: proxies ÷ assigned × 100.
        $cells[15] = new FormulaCell('=(M'.$row.'/E'.$row.')*100', null, $this->blockStyle(15, 15, 16, $row, $bottomRow, $row));
        $cells[16] = new FormulaCell('=(N'.$row.'/F'.$row.')*100', null, $this->blockStyle(16, 15, 16, $row, $bottomRow, $row));

        foreach ($data->days() as $dayIndex => $day) {
            $start = self::FIRST_DAY_COLUMN + $dayIndex * 4;
            $stats = $data->dayStats($worker['key'], $day);
            $values = $stats === null
                ? ['', '', '', '']
                : [$stats['reports'], $stats['sharesContacted'], $stats['proxyShareholders'], $stats['proxyShares']];

            foreach ($values as $offset => $value) {
                $cells[$start + $offset] = Cell::fromValue($value, $this->blockStyle($start + $offset, $start, $start + 3, $row, $bottomRow, $row));
            }
        }

        return $this->fillEmpty($cells);
    }

    /**
     * Totals row (blue, thick top border): SUM over the worker rows for the
     * targets, the 누계 columns, and every day column.
     *
     * @return list<Cell>
     */
    private function totalsRow(ActivityData $data, int $workerCount, int $lastColumn, int $bottomRow): array
    {
        $row = $workerCount + 3;
        $cells = array_fill(1, $lastColumn, null);

        $sum = fn (string $column): string => '=ROUND(SUM('.$column.'3:'.$column.($workerCount + 2).'),0)';

        foreach ([5 => 'E', 6 => 'F'] as $column => $letter) {
            $cells[$column] = new FormulaCell($sum($letter), null, $this->blockStyle($column, 5, 6, $row, $bottomRow, $row, fontColor: self::FONT_BLUE, thickTop: true));
        }
        foreach ([11 => 'K', 12 => 'L', 13 => 'M', 14 => 'N'] as $column => $letter) {
            $cells[$column] = new FormulaCell($sum($letter), null, $this->blockStyle($column, 11, 14, $row, $bottomRow, $row, fontColor: self::FONT_BLUE, thickTop: true));
        }

        foreach (array_keys($data->days()) as $dayIndex) {
            $start = self::FIRST_DAY_COLUMN + $dayIndex * 4;
            for ($offset = 0; $offset <= 3; $offset++) {
                $column = $start + $offset;
                $letter = $this->columnLetter($column);
                $cells[$column] = new FormulaCell(
                    '=SUM('.$letter.'3:'.$letter.($workerCount + 2).')',
                    null,
                    $this->blockStyle($column, $start, $start + 3, $row, $bottomRow, $row, fontColor: self::FONT_BLUE, thickTop: true),
                );
            }
        }

        // The fixed blocks still need their side borders on this row.
        $cells[4] = Cell::fromValue('', $this->blockStyle(4, 4, 4, $row, $bottomRow, $row));
        $cells[7] = Cell::fromValue('', $this->blockStyle(7, 7, 10, $row, $bottomRow, $row));
        foreach ([8, 9, 10] as $column) {
            $cells[$column] = Cell::fromValue('', $this->blockStyle($column, 7, 10, $row, $bottomRow, $row));
        }
        $cells[15] = Cell::fromValue('', $this->blockStyle(15, 15, 16, $row, $bottomRow, $row));
        $cells[16] = Cell::fromValue('', $this->blockStyle(16, 15, 16, $row, $bottomRow, $row));

        return $this->fillEmpty($cells);
    }

    /**
     * Averages row (red). Day columns and 누계 divide their totals (counts to
     * one decimal, shares to zero); targets divide by the worker count; the
     * G–J / O–P columns use real AVERAGE() formulas (the legacy-bug fix).
     *
     * @return list<Cell>
     */
    private function averagesRow(ActivityData $data, int $workerCount, int $lastColumn, int $bottomRow): array
    {
        $row = $workerCount + 4;
        $totalsRow = $workerCount + 3;
        $cells = array_fill(1, $lastColumn, null);

        $average = fn (string $column, int $decimals): string => '=ROUND(AVERAGE('.$column.'3:'.$column.($workerCount + 2).'),'.$decimals.')';

        foreach ([5 => 'E', 6 => 'F'] as $column => $letter) {
            $cells[$column] = new FormulaCell(
                '=ROUND('.$letter.$totalsRow.'/'.$workerCount.',0)',
                null,
                $this->blockStyle($column, 5, 6, $row, $bottomRow, $row, fontColor: self::FONT_RED),
            );
        }

        // 일 평균 + 위임률: legacy divided an empty totals cell (always 0);
        // real averages of the worker rows instead. H stays blank like legacy.
        foreach ([7 => 1, 9 => 1, 10 => 0, 15 => 1, 16 => 1] as $column => $decimals) {
            $blockStart = $column >= 15 ? 15 : 7;
            $blockEnd = $column >= 15 ? 16 : 10;
            $cells[$column] = new FormulaCell(
                $average($this->columnLetter($column), $decimals),
                null,
                $this->blockStyle($column, $blockStart, $blockEnd, $row, $bottomRow, $row, fontColor: self::FONT_RED),
            );
        }

        foreach ([11 => 1, 12 => 0, 13 => 1, 14 => 0] as $column => $decimals) {
            $letter = $this->columnLetter($column);
            $cells[$column] = new FormulaCell(
                '=ROUND('.$letter.$totalsRow.'/'.$workerCount.','.$decimals.')',
                null,
                $this->blockStyle($column, 11, 14, $row, $bottomRow, $row, fontColor: self::FONT_RED),
            );
        }

        foreach (array_keys($data->days()) as $dayIndex) {
            $start = self::FIRST_DAY_COLUMN + $dayIndex * 4;
            for ($offset = 0; $offset <= 3; $offset++) {
                $column = $start + $offset;
                $letter = $this->columnLetter($column);
                // Counts round to one decimal, share sums to zero (legacy rule).
                $decimals = $offset % 2 === 0 ? 1 : 0;
                $cells[$column] = new FormulaCell(
                    '=ROUND('.$letter.$totalsRow.'/COUNTA('.$letter.'3:'.$letter.($workerCount + 2).'),'.$decimals.')',
                    null,
                    $this->blockStyle($column, $start, $start + 3, $row, $bottomRow, $row, fontColor: self::FONT_RED),
                );
            }
        }

        $cells[4] = Cell::fromValue('', $this->blockStyle(4, 4, 4, $row, $bottomRow, $row));
        $cells[8] = Cell::fromValue('', $this->blockStyle(8, 7, 10, $row, $bottomRow, $row));

        return $this->fillEmpty($cells);
    }

    /**
     * The style for a cell inside a thick-boxed block: thick border on the
     * block's outer edges (row 1 … the averages row), thin inside, plus
     * optional fill / font colour / centering / thick top (totals row).
     */
    private function blockStyle(
        int $column,
        int $blockStart,
        int $blockEnd,
        int $row,
        int $bottomRow,
        int $currentRow,
        ?string $fill = null,
        ?string $fontColor = null,
        bool $center = false,
        bool $thickTop = false,
    ): Style {
        $left = $column === $blockStart ? BorderWidth::THICK : BorderWidth::THIN;
        $right = $column === $blockEnd ? BorderWidth::THICK : BorderWidth::THIN;
        $top = ($currentRow === 1 || $thickTop) ? BorderWidth::THICK : BorderWidth::THIN;
        $bottom = $currentRow === $bottomRow ? BorderWidth::THICK : BorderWidth::THIN;

        return $this->style($fill, $fontColor, $center, [$left, $right, $top, $bottom]);
    }

    /**
     * Memoized style factory — OpenSpout registers one style per instance, so
     * identical traits must share an instance.
     *
     * @param  array{0: BorderWidth, 1: BorderWidth, 2: BorderWidth, 3: BorderWidth}|null  $borders  left/right/top/bottom
     */
    private function style(
        ?string $fill = null,
        ?string $fontColor = null,
        bool $center = false,
        ?array $borders = null,
    ): Style {
        $key = ($fill ?? '-').'|'.($fontColor ?? '-').'|'.($center ? 'c' : '-').'|'
            .($borders === null ? '-' : implode(',', array_map(fn (BorderWidth $width): string => $width->value, $borders)));

        if (isset($this->styles[$key])) {
            return $this->styles[$key];
        }

        $style = new Style;

        if ($fill !== null) {
            $style = $style->withBackgroundColor($fill);
        }

        if ($fontColor !== null) {
            $style = $style->withFontColor($fontColor);
        }

        if ($center) {
            $style = $style
                ->withCellAlignment(CellAlignment::CENTER)
                ->withCellVerticalAlignment(CellVerticalAlignment::CENTER);
        }

        if ($borders !== null) {
            [$left, $right, $top, $bottom] = $borders;
            $style = $style->withBorder(new Border(
                new BorderPart(BorderName::LEFT, width: $left, style: BorderStyle::SOLID),
                new BorderPart(BorderName::RIGHT, width: $right, style: BorderStyle::SOLID),
                new BorderPart(BorderName::TOP, width: $top, style: BorderStyle::SOLID),
                new BorderPart(BorderName::BOTTOM, width: $bottom, style: BorderStyle::SOLID),
            ));
        }

        return $this->styles[$key] = $style;
    }

    /**
     * @param  array<int, Cell|null>  $cells  1-based column => cell
     * @return list<Cell>
     */
    private function fillEmpty(array $cells): array
    {
        ksort($cells);

        return array_values(array_map(fn (?Cell $cell): Cell => $cell ?? Cell::fromValue(''), $cells));
    }

    /**
     * 1-based column index → A1-notation letters (17 → Q, 27 → AA).
     */
    private function columnLetter(int $column): string
    {
        $letters = '';

        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $letters = chr(65 + $remainder).$letters;
            $column = intdiv($column - 1 - $remainder, 26);
        }

        return $letters;
    }
}
