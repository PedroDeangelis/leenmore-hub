<?php

namespace App\Exports;

use App\Enums\RosterSortColumn;
use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\User;

/**
 * The 명부양식 (worker roster form) data — the Laravel port of the legacy
 * compose page's `buildWorkerReportPayload`. One row per shareholder
 * assignment the worker canvasses on the project, keyed by template column
 * letter (A–L), ordered by the admin's chosen sort column/direction.
 *
 * The rows feed the template-fill XLSX writer and the mPDF fallback, so values
 * are plain scalars: ints stay numeric (Excel number cells), everything else
 * is a string.
 */
class WorkerRosterExporter
{
    /**
     * The template's column headers in A–L order (matches
     * resources/templates/email-to-worker.xlsx row 2) — used by the PDF
     * fallback layout.
     *
     * @var list<string>
     */
    public const HEADERS = [
        '연번',           // A
        '고유번호',        // B (name + DOB code)
        '성별',           // C
        '비고',           // D (prev_note)
        '총소유주식수',     // E
        '주소',           // F
        '주소서치',        // G
        '주소서치2',       // H
        '구연락처',        // I (source_database)
        '구 판단',         // J
        '구 멘트',         // K
        '연락처',          // L (contact_worker)
    ];

    private function __construct(
        private readonly Project $project,
        private readonly User $worker,
        private readonly RosterSortColumn $sortColumn,
        private readonly bool $sortDesc,
    ) {}

    public static function for(
        Project $project,
        User $worker,
        RosterSortColumn $sortColumn = RosterSortColumn::No,
        bool $sortDesc = false,
    ): self {
        return new self($project, $worker, $sortColumn, $sortDesc);
    }

    /**
     * The worker's roster rows, sorted. Empty when the worker has no
     * assignments on this project (legacy: such workers get no 명부양식).
     *
     * @return list<array<string, int|string>>
     */
    public function rows(): array
    {
        return $this->project->shareholders()
            ->whereHas('workers', fn ($query) => $query->where('users.id', $this->worker->id))
            ->with('shareholder')
            ->get()
            ->sort(fn (ProjectShareholder $a, ProjectShareholder $b): int => $this->compare($a, $b))
            ->values()
            ->map(fn (ProjectShareholder $assignment): array => $this->row($assignment))
            ->all();
    }

    /**
     * @return array<string, int|string>
     */
    private function row(ProjectShareholder $assignment): array
    {
        $person = $assignment->shareholder;

        return [
            'A' => $assignment->no ?? '',
            // 고유번호 = 주주명 + 생년월일 6자리; blank without a DOB code (legacy rule).
            'B' => filled($person?->date_of_birth_code) ? $person->name.$person->date_of_birth_code : '',
            'C' => $person?->sex ?? '',
            'D' => $assignment->prev_note ?? '',
            'E' => $assignment->shares_total ?? '',
            'F' => $assignment->effective_address ?? '',
            'G' => $assignment->effective_contact ?? '',
            'H' => $assignment->contact_info_2 ?? '',
            'I' => $assignment->source_database ?? '',
            'J' => $assignment->prev_result ?? '',
            'K' => $assignment->prev_comment ?? '',
            'L' => $assignment->contact_worker ?? '',
        ];
    }

    private function compare(ProjectShareholder $a, ProjectShareholder $b): int
    {
        $valueA = $this->sortColumn->valueFor($a);
        $valueB = $this->sortColumn->valueFor($b);

        $result = $this->sortColumn->isNumeric()
            ? ($this->compareNumeric($valueA, $valueB) ?? strnatcasecmp($valueA, $valueB))
            : strnatcasecmp($valueA, $valueB);

        return $this->sortDesc ? -$result : $result;
    }

    /**
     * Numeric comparison after stripping non-numeric characters (legacy
     * `parseSortableNumber`); null when either side isn't a number so the
     * caller can fall back to text comparison.
     */
    private function compareNumeric(string $a, string $b): ?int
    {
        $numberA = $this->sortableNumber($a);
        $numberB = $this->sortableNumber($b);

        if ($numberA === null || $numberB === null) {
            return null;
        }

        return $numberA <=> $numberB;
    }

    private function sortableNumber(string $value): ?float
    {
        $cleaned = preg_replace('/[^0-9.\-]/', '', $value) ?? '';

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }
}
