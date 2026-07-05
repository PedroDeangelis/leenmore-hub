<?php

namespace App\Exports;

use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Submission;
use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The 활동내역(엑셀) export — the Laravel port of the legacy React app's
 * `getDownloadCSV.js`. One row per shareholder assignment: a fixed block of
 * roster columns, followed by a per-day block (담당자 / 멘트 / 연락처 정보 / 판단)
 * for every calendar day in the project's submission period.
 *
 * Per-day cells aggregate that shareholder's reports on that day: notes and
 * worker-contacts accumulate across every report, while the assignee and 판단
 * come from the latest report of the day (date, then created_at, then id).
 *
 * Built once per project via {@see for()}; {@see headers()} and {@see rows()}
 * then drive the spreadsheet writer.
 */
class ActivityLogExporter
{
    private const PRIVACY_CONSENT_LABEL = '동의';

    private const ASSIGNEE_PREFIX = '담당자';

    private const SCRIPT_PREFIX = '멘트';

    private const CONTACT_PREFIX = '연락처 정보';

    private const RESULT_PREFIX = '판단';

    /**
     * The fixed roster columns, in order — the exact Korean headers the firm
     * sees in the legacy export (one English passthrough: "Contact 2").
     *
     * @var list<string>
     */
    private const BASE_HEADERS = [
        '연번',            // No
        '발행회사',         // Company (project title)
        '실명번호',         // Resident registration number
        '고유번호',         // Name + first 6 digits of RRN
        '성별',            // Sex
        '주주명',           // Name
        '주식수',           // Number of shares
        '총소유주식수',      // Total number of shares
        '전자투표',         // Electronic voting
        '주소',            // Address
        '주소서치',         // Contact (contact_info, person fallback)
        'Contact 2',       // Contact 2 (untranslated in the legacy app)
        '구연락처',         // Database (source_database)
        '연락처',           // contact_worker
        '담당자',           // Worker(s)
        '제출 날짜',        // Latest submission date
        '판단',            // Current result
        '개인정보 동의',     // Privacy consent file
        '전자위임연락처',    // Recipient contact
        '전자위임날짜',      // Completion date
    ];

    /**
     * MMDD keys for every day in the submission period, earliest to latest.
     *
     * @var list<string>
     */
    private array $dayKeys = [];

    /**
     * Per-assignment aggregates, keyed by project_shareholder_id.
     *
     * @var array<int, array{byDate: array<string, array{user: string, notes: list<string>, contacts: list<string>, result: string}>, latestSubmissionDate: string, latestCreatedAt: ?Carbon, privacyConsent: bool}>
     */
    private array $lookup = [];

    private function __construct(private readonly Project $project) {}

    /**
     * Build the exporter for a project, eagerly computing the day range and the
     * per-day submission aggregates.
     */
    public static function for(Project $project): self
    {
        $exporter = new self($project);
        $exporter->prepare();

        return $exporter;
    }

    /**
     * The header row: the fixed roster columns plus four columns per day.
     *
     * @return list<string>
     */
    public function headers(): array
    {
        $headers = self::BASE_HEADERS;

        foreach ($this->dayKeys as $day) {
            $headers[] = self::ASSIGNEE_PREFIX.$day;
            $headers[] = self::SCRIPT_PREFIX.$day;
            $headers[] = self::CONTACT_PREFIX.$day;
            $headers[] = self::RESULT_PREFIX.$day;
        }

        return $headers;
    }

    /**
     * One row per shareholder assignment, streamed in roster order so a large
     * project never has to hold every row in memory at once.
     *
     * @return Generator<int, list<int|string|null>>
     */
    public function rows(): Generator
    {
        $assignments = $this->project->shareholders()
            ->with(['shareholder', 'workers', 'result'])
            ->lazy();

        foreach ($assignments as $assignment) {
            yield $this->row($assignment);
        }
    }

    /**
     * Load the project's reports once, then derive the day range and the
     * per-assignment / per-day aggregates from them.
     */
    private function prepare(): void
    {
        $submissions = Submission::query()
            ->where('project_id', $this->project->id)
            ->orderBy('date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'project_shareholder_id', 'date', 'created_at', 'user_name', 'note', 'contact', 'result', 'privacy_consent']);

        $this->dayKeys = $this->buildDayKeys($submissions);
        $this->lookup = $this->buildLookup($submissions);
    }

    /**
     * Every MMDD day from the earliest to the latest dated report, inclusive.
     *
     * @param  Collection<int, Submission>  $submissions  ordered by (date, created_at, id)
     * @return list<string>
     */
    private function buildDayKeys(Collection $submissions): array
    {
        $dated = $submissions->filter(fn (Submission $s): bool => $s->date !== null);

        if ($dated->isEmpty()) {
            return [];
        }

        $cursor = $dated->first()->date->startOfDay();
        $end = $dated->last()->date->startOfDay();

        $keys = [];

        // Dates are CarbonImmutable, so advance by reassigning, not mutating.
        while ($cursor->lte($end)) {
            $keys[] = $cursor->format('md');
            $cursor = $cursor->addDay();
        }

        return $keys;
    }

    /**
     * Fold the reports into per-assignment aggregates. Reports arrive ordered by
     * (date, created_at, id), so within a day the last one seen is the latest.
     *
     * @param  Collection<int, Submission>  $submissions
     * @return array<int, array{byDate: array<string, array{user: string, notes: list<string>, contacts: list<string>, result: string}>, latestSubmissionDate: string, latestCreatedAt: ?Carbon, privacyConsent: bool}>
     */
    private function buildLookup(Collection $submissions): array
    {
        $lookup = [];

        foreach ($submissions as $submission) {
            $id = $submission->project_shareholder_id;

            if ($id === null) {
                continue;
            }

            $lookup[$id] ??= [
                'byDate' => [],
                'latestSubmissionDate' => '',
                'latestCreatedAt' => null,
                'privacyConsent' => false,
            ];

            // 제출 날짜: the created_at of the most recently filed report.
            $createdAt = $submission->created_at ?? $submission->date;

            if ($createdAt !== null
                && ($lookup[$id]['latestCreatedAt'] === null || $createdAt->gte($lookup[$id]['latestCreatedAt']))) {
                $lookup[$id]['latestCreatedAt'] = $createdAt;
                $lookup[$id]['latestSubmissionDate'] = $createdAt->format('Y-m-d');
            }

            if ($submission->privacy_consent) {
                $lookup[$id]['privacyConsent'] = true;
            }

            if ($submission->date === null) {
                continue;
            }

            $day = $submission->date->format('md');

            $lookup[$id]['byDate'][$day] ??= [
                'user' => '',
                'notes' => [],
                'contacts' => [],
                'result' => '',
            ];

            if (trim((string) $submission->note) !== '') {
                $lookup[$id]['byDate'][$day]['notes'][] = $submission->note;
            }

            if (trim((string) $submission->contact) !== '') {
                $lookup[$id]['byDate'][$day]['contacts'][] = $submission->contact;
            }

            // Latest report of the day wins for the assignee and 판단.
            $lookup[$id]['byDate'][$day]['user'] = $submission->user_name ?? '';
            $lookup[$id]['byDate'][$day]['result'] = $submission->result ?? '';
        }

        return $lookup;
    }

    /**
     * Build one spreadsheet row for an assignment.
     *
     * @return list<int|string|null>
     */
    private function row(ProjectShareholder $assignment): array
    {
        $person = $assignment->shareholder;
        $data = $this->lookup[$assignment->id] ?? null;

        $row = [
            $assignment->no,
            $this->project->title,
            $this->registration($person?->registration),
            ($person?->name ?? '').($person?->date_of_birth_code ?? ''),
            $person?->sex ?? '',
            $person?->name ?? '',
            $assignment->shares,
            $assignment->shares_total,
            $this->bool($assignment->electronic_voting),
            $assignment->effective_address ?? '',
            $assignment->effective_contact ?? '',
            $assignment->contact_info_2 ?? '',
            $assignment->source_database ?? '',
            $assignment->contact_worker ?? '',
            $assignment->workers->pluck('name')->join('/'),
            $data['latestSubmissionDate'] ?? '',
            $assignment->result?->name ?? '',
            ($data['privacyConsent'] ?? false) ? self::PRIVACY_CONSENT_LABEL : '',
            $assignment->api_recipient_contact ?? '',
            $assignment->api_recipient_completion_date?->format('Y-m-d') ?? '',
        ];

        foreach ($this->dayKeys as $day) {
            $entry = $data['byDate'][$day] ?? null;

            $row[] = $entry['user'] ?? '';
            $row[] = isset($entry['notes']) ? implode(' / ', $entry['notes']) : '';
            $row[] = isset($entry['contacts']) ? implode(' / ', $entry['contacts']) : '';
            $row[] = $entry['result'] ?? '';
        }

        return $row;
    }

    /**
     * Re-hyphenate a 13-digit personal RRN (YYMMDD-Sxxxxxx); leave corporate /
     * partial numbers as stored.
     */
    private function registration(?string $digits): string
    {
        if ($digits === null || $digits === '') {
            return '';
        }

        return strlen($digits) === 13
            ? substr($digits, 0, 6).'-'.substr($digits, 6)
            : $digits;
    }

    private function bool(?bool $value): string
    {
        return match ($value) {
            true => 'Y',
            false => 'N',
            null => '',
        };
    }
}
