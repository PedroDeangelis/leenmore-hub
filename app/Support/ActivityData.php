<?php

namespace App\Support;

use App\Enums\ResultColor;
use App\Models\Project;
use App\Models\Submission;

/**
 * The 활동 데이터 (Activity Data) analytics: per-worker contact/proxy statistics
 * over a project's activity reports, optionally narrowed to selected activity
 * days. Shared by the analytics page and the Excel export so both always show
 * the same numbers (the JudgmentTally pattern). Port of the legacy
 * `getSubmissionActivityData` / `downloadExcelActivityData` math.
 *
 * Semantics carried over from the legacy app:
 * - A report is a 위임 (proxy) when its result name matches one of the
 *   project's 판단 whose colour is green.
 * - 접촉 횟수 on screen counts EVERY report (repeat contacts included); the
 *   share/proxy figures dedupe to each shareholder's latest report
 *   (date → created_at → id, matching ActivityLogExporter).
 * - The Excel day blocks dedupe per shareholder WITHIN each day, so the
 *   workbook's cumulative 누계 can exceed the screen's globally-deduped
 *   figures — intentional legacy behaviour.
 */
final class ActivityData
{
    /**
     * Per-worker rows, ordered by worker name.
     *
     * @var list<array{key: string, name: string, totalReports: int, sharesContacted: int,
     *     proxyShareholders: int, proxyShares: int, daysWorked: int, shareholderCount: int,
     *     assignedShares: int}>
     */
    private array $workers = [];

    /**
     * Per-worker, per-day stats for the Excel day blocks (within-day dedup).
     *
     * @var array<string, array<string, array{reports: int, sharesContacted: int,
     *     proxyShareholders: int, proxyShares: int}>>
     */
    private array $dayStats = [];

    /** @var list<string> every activity day of the project (Y-m-d, ascending) */
    private array $allDays = [];

    /** @var list<string> activity days present in the filtered set (Y-m-d, ascending) */
    private array $days = [];

    private function __construct() {}

    /**
     * Compute the analytics for a project, optionally restricted to the given
     * activity days (Y-m-d strings; null/empty = all days).
     *
     * @param  list<string>|null  $days
     */
    public static function for(Project $project, ?array $days = null): self
    {
        $data = new self;

        // 판단 whose colour is green mark a granted proxy (위임).
        $greenNames = $project->results
            ->filter(fn ($result): bool => $result->color === ResultColor::Green)
            ->pluck('name')
            ->flip()
            ->all();

        // Ascending (date, created_at, id) so "overwrite as we go" keeps each
        // shareholder's latest report — the ActivityLogExporter convention.
        $submissions = $project->submissions()
            ->reorder()
            ->orderBy('date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->with(['projectShareholder', 'user'])
            ->get();

        $data->allDays = $submissions
            ->map(fn (Submission $submission): string => (string) $submission->date?->format('Y-m-d'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($days !== null && $days !== []) {
            $wanted = array_flip($days);
            $submissions = $submissions
                ->filter(fn (Submission $submission): bool => isset($wanted[(string) $submission->date?->format('Y-m-d')]))
                ->values();
        }

        $data->days = $submissions
            ->map(fn (Submission $submission): string => (string) $submission->date?->format('Y-m-d'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $workers = [];

        foreach ($submissions as $submission) {
            // Real user FK when available; the snapshot name keeps orphaned
            // legacy rows grouped sensibly.
            $key = $submission->user_id !== null ? 'u'.$submission->user_id : 'n'.$submission->user_name;

            $workers[$key] ??= [
                'name' => $submission->user?->name ?? (string) $submission->user_name,
                'totalReports' => 0,
                'dates' => [],
                'shareholders' => [],
                'assignedShares' => 0,
                'latest' => [],
                'dayLatest' => [],
            ];

            $day = (string) $submission->date?->format('Y-m-d');
            $shareholderKey = $submission->project_shareholder_id !== null
                ? 'ps'.$submission->project_shareholder_id
                : 'sub'.$submission->id;

            $workers[$key]['totalReports']++;
            $workers[$key]['dates'][$day] = true;

            if (! isset($workers[$key]['shareholders'][$shareholderKey])) {
                $workers[$key]['shareholders'][$shareholderKey] = true;
                $workers[$key]['assignedShares'] += self::shares($submission);
            }

            $workers[$key]['latest'][$shareholderKey] = $submission;
            $workers[$key]['dayLatest'][$day][$shareholderKey] = $submission;
        }

        foreach ($workers as $key => $worker) {
            $row = [
                'key' => $key,
                'name' => $worker['name'],
                'totalReports' => $worker['totalReports'],
                'sharesContacted' => 0,
                'proxyShareholders' => 0,
                'proxyShares' => 0,
                'daysWorked' => count($worker['dates']),
                'shareholderCount' => count($worker['shareholders']),
                'assignedShares' => $worker['assignedShares'],
            ];

            foreach ($worker['latest'] as $submission) {
                $shares = self::shares($submission);
                $row['sharesContacted'] += $shares;

                if (self::isProxy($submission, $greenNames)) {
                    $row['proxyShareholders']++;
                    $row['proxyShares'] += $shares;
                }
            }

            $data->workers[] = $row;

            foreach ($worker['dayLatest'] as $day => $latestPerShareholder) {
                $stats = ['reports' => 0, 'sharesContacted' => 0, 'proxyShareholders' => 0, 'proxyShares' => 0];

                foreach ($latestPerShareholder as $submission) {
                    $shares = self::shares($submission);
                    $stats['reports']++;
                    $stats['sharesContacted'] += $shares;

                    if (self::isProxy($submission, $greenNames)) {
                        $stats['proxyShareholders']++;
                        $stats['proxyShares'] += $shares;
                    }
                }

                $data->dayStats[$key][$day] = $stats;
            }
        }

        usort($data->workers, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $data;
    }

    /**
     * @return list<string>
     */
    public function allDays(): array
    {
        return $this->allDays;
    }

    /**
     * @return list<string>
     */
    public function days(): array
    {
        return $this->days;
    }

    /**
     * @return list<array{key: string, name: string, totalReports: int, sharesContacted: int,
     *     proxyShareholders: int, proxyShares: int, daysWorked: int, shareholderCount: int,
     *     assignedShares: int}>
     */
    public function workers(): array
    {
        return $this->workers;
    }

    /**
     * The three summary cards: distinct workers, total proxy shareholders,
     * total proxy shares.
     *
     * @return array{workers: int, proxyShareholders: int, proxyShares: int}
     */
    public function summary(): array
    {
        return [
            'workers' => count($this->workers),
            'proxyShareholders' => array_sum(array_column($this->workers, 'proxyShareholders')),
            'proxyShares' => array_sum(array_column($this->workers, 'proxyShares')),
        ];
    }

    /**
     * One worker's stats for one day (Excel day block), or null when the
     * worker filed nothing that day.
     *
     * @return array{reports: int, sharesContacted: int, proxyShareholders: int, proxyShares: int}|null
     */
    public function dayStats(string $workerKey, string $day): ?array
    {
        return $this->dayStats[$workerKey][$day] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->workers === [];
    }

    private static function shares(Submission $submission): int
    {
        return (int) ($submission->projectShareholder?->shares ?? 0);
    }

    /**
     * @param  array<string, int>  $greenNames
     */
    private static function isProxy(Submission $submission, array $greenNames): bool
    {
        return $submission->result !== null && isset($greenNames[$submission->result]);
    }
}
