<?php

namespace App\Support;

use App\Support\Supabase\Judgment;
use App\Support\Supabase\Num;
use App\Support\Supabase\ProfileRepository;
use App\Support\Supabase\ProjectRepository;
use App\Support\Supabase\ReceiptRepository;
use App\Support\Supabase\ResultSet;
use App\Support\Supabase\ShareholderRepository;
use App\Support\Supabase\SubmissionRepository;
use Illuminate\Support\Carbon;

/**
 * The read layer behind the REST API (app/Http/Controllers/Api). One method per
 * endpoint, each taking already-validated params and returning a plain array.
 *
 * Both the controllers and the in-app "Try it" tester call these, so what a
 * developer sees documented and what the endpoint returns cannot drift.
 *
 * Everything comes from Supabase, which is the client's own system of record and
 * is only ever read. Three of its habits are handled here rather than leaked to
 * callers: numbers are comma-formatted text ({@see Num}), 판단 are a text[] of
 * JSON strings referenced by array index ({@see ResultSet}), and workers are
 * uuids on submissions but names on shareholder rows ({@see ProfileRepository}).
 *
 * Personal data goes through {@see Pii} on the way out: 실명번호 masked, contacts
 * reported only as present-or-not, and free-text notes returned with phone numbers,
 * 실명번호 and emails redacted.
 */
final class ApiData
{
    private const PER_PAGE_DEFAULT = 50;

    private const PER_PAGE_MAX = 200;

    /**
     * The 판단 an electronic proxy (전자위임) is reported under. It has no entry in
     * any project's option list; the name is the one the client's admin shows.
     */
    private const ESIGNON_RESULT = '위임(전자위임)';

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function projects(array $params): array
    {
        $filters = [];

        match ($params['status'] ?? null) {
            // No soft-delete exists upstream; "active" means not yet archived.
            'active' => $filters['status'] = 'in.(draft,publish)',
            null, '' => null,
            default => $filters['status'] = 'eq.'.$params['status'],
        };

        if (filled($params['search'] ?? null)) {
            // ilike, not like: Postgres LIKE is case-sensitive.
            $filters['title'] = 'ilike.*'.$params['search'].'*';
        }

        if (filled($params['from'] ?? null)) {
            $filters['start_date'] = 'gte.'.$params['from'];
        }

        if (filled($params['to'] ?? null)) {
            // Inclusive of the whole day, since start_date carries a time.
            $filters['and'] = '(start_date.lte.'.$params['to'].'T23:59:59)';
        }

        [$page, $perPage] = self::pageParams($params);
        $result = self::projectRepository()->paginate($filters, $page, $perPage);

        return self::envelope(array_map(static function (array $row): array {
            $counts = self::projectRepository()->shareholderCounts((int) $row['id']);

            return [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'status' => $row['status'],
                'start_date' => self::date($row['start_date'] ?? null),
                'meeting_date' => self::date($row['end_date'] ?? null),
                'days_until_meeting' => self::daysUntil($row['end_date'] ?? null),
                'shares_issued' => Num::int($row['shares_issued'] ?? null),
                'shares_target' => Num::int($row['shares_target'] ?? null),
                'shareholder_count' => $counts['total'],
            ];
        }, $result['rows']), $result['total'], $page, $perPage);
    }

    /**
     * @return array<string, mixed>|null null when the project does not exist
     */
    public static function project(int $projectId): ?array
    {
        $row = self::projectRepository()->find($projectId);

        if ($row === null) {
            return null;
        }

        $results = ResultSet::fromColumn($row['results'] ?? null);
        $counts = self::projectRepository()->shareholderCounts($projectId);
        $target = Num::int($row['shares_target'] ?? null);

        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'status' => $row['status'],
            'message' => $row['message'] ?? null,
            'start_date' => self::date($row['start_date'] ?? null),
            'meeting_date' => self::date($row['end_date'] ?? null),
            'days_until_meeting' => self::daysUntil($row['end_date'] ?? null),
            'shares_issued' => Num::int($row['shares_issued'] ?? null),
            'shares_target' => $target,
            'shareholder_count' => $counts['total'],
            'judged_shareholder_count' => $counts['judged'],
            'unjudged_shareholder_count' => $counts['total'] - $counts['judged'],
            'results' => $results->toArray(),
            'judgment_tally' => self::judgmentTally($projectId, $results, $target),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    public static function shareholders(int $projectId, array $params): ?array
    {
        $row = self::projectRepository()->find($projectId);

        if ($row === null) {
            return null;
        }

        $results = ResultSet::fromColumn($row['results'] ?? null);
        $filters = [];

        // A shareholder's `result` is an index into the project's option list,
        // so filtering by judgment means resolving names/proxies to indices first.
        if (filled($params['result'] ?? null)) {
            $indices = array_values(array_map(
                static fn (Judgment $j): string => (string) $j->index,
                array_filter($results->judgments, static fn (Judgment $j): bool => $j->name === $params['result']),
            ));

            // No such judgment on this project: an empty page, not every row.
            $filters['result'] = $indices === [] ? 'in.(-1)' : 'in.('.implode(',', $indices).')';
        }

        if (self::boolish($params['proxy_only'] ?? null)) {
            $proxies = $results->proxyIndices();
            $filters['result'] = $proxies === [] ? 'in.(-1)' : 'in.('.implode(',', $proxies).')';
        }

        if (array_key_exists('judged', $params) && $params['judged'] !== null && $params['judged'] !== '') {
            $filters['result'] = self::boolish($params['judged']) ? 'not.is.null' : 'is.null';
        }

        if (filled($params['worker_id'] ?? null)) {
            $filters['user'] = self::workerNameFilter((string) $params['worker_id']);
        }

        if (filled($params['search'] ?? null)) {
            $filters['name'] = 'ilike.*'.$params['search'].'*';
        }

        [$page, $perPage] = self::pageParams($params);
        $result = self::shareholderRepository()->paginateForProject($projectId, $filters, $page, $perPage);

        return self::envelope(array_map(static function (array $row) use ($results): array {
            $judgment = $results->resolve($row['result'] ?? null);

            return [
                // One Supabase row is one person on one project; there is no
                // separate global person, so this id is both.
                'id' => (int) $row['id'],
                'no' => $row['no'] === null ? null : (int) $row['no'],
                'name' => $row['name'] ?? null,
                'person_type' => $row['person_type'] ?? null,
                'registration_masked' => Pii::registration($row['registration'] ?? null),
                'birth_year' => self::birthYear($row['registration'] ?? null),
                'region' => Pii::address($row['address'] ?? null),
                'has_contact' => Pii::hasContact($row['contact_info'] ?? null, $row['contact_info_2'] ?? null),
                'shares' => Num::int($row['shares'] ?? null),
                'shares_total' => Num::int($row['shares_total'] ?? null),
                'result' => $judgment?->name,
                'result_color' => $judgment?->color,
                'is_proxy' => $judgment?->isProxy() ?? false,
                'workers' => array_values(array_filter($row['user'] ?? [])),
            ];
        }, $result['rows']), $result['total'], $page, $perPage);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function submissions(array $params): array
    {
        $filters = [];

        if (filled($params['project_id'] ?? null)) {
            $filters['project_id'] = 'eq.'.$params['project_id'];
        }

        if (filled($params['worker_id'] ?? null)) {
            // Submissions do carry a real profile uuid, unlike roster rows.
            $filters['user_id'] = 'eq.'.$params['worker_id'];
        }

        if (filled($params['from'] ?? null)) {
            $filters['date'] = 'gte.'.$params['from'];
        }

        if (filled($params['to'] ?? null)) {
            $filters['and'] = '(date.lte.'.$params['to'].'T23:59:59)';
        }

        $result = $params['result'] ?? null;
        $onlyEproxies = $result === self::ESIGNON_RESULT;

        // 전자위임 join only a project-scoped list: across all projects the
        // lookup is a full scan of the roster.
        $eproxies = filled($params['project_id'] ?? null) && (blank($result) || $onlyEproxies)
            ? self::eproxies((int) $params['project_id'], $params)
            : [];

        [$page, $perPage] = self::pageParams($params);
        $offset = ($page - 1) * $perPage;

        // At most count($eproxies) rows ahead of this page are e-proxies, so every
        // report that can land on it sits at or after $start.
        $start = max(0, $offset - count($eproxies));

        $reports = $onlyEproxies
            ? ['rows' => [], 'total' => 0]
            : self::submissionRepository()->slice($filters, $start, $offset + $perPage - $start);

        // Judgment names are per-project, so decode each project's option list
        // once rather than per row.
        $resultSets = self::resultSetsFor(array_column($reports['rows'], 'project_id'));

        $rows = array_map(static function (array $row) use ($resultSets): array {
            $judgment = ($resultSets[(int) $row['project_id']] ?? null)?->resolve($row['result'] ?? null);

            return [
                'id' => (int) $row['id'],
                'source' => 'worker',
                'date' => self::date($row['date'] ?? null),
                // One legacy row upstream has no project; report that rather
                // than casting it to a project 0 that does not exist.
                'project_id' => isset($row['project_id']) ? (int) $row['project_id'] : null,
                'shareholder_id' => isset($row['shareholder_id']) ? (int) $row['shareholder_id'] : null,
                'worker_id' => $row['user_id'] ?? null,
                'worker_name' => $row['user_name'] ?? null,
                'result' => $judgment?->name,
                'result_color' => $judgment?->color,
                'is_proxy' => $judgment?->isProxy() ?? false,
                'has_contact' => Pii::hasContact($row['contact_worker'] ?? null),
                'privacy_consent' => filled($row['privacy_consent_file'] ?? null),
                'file_count' => count($row['files'] ?? []),
                'note' => Pii::note($row['note'] ?? null),
            ];
        }, $reports['rows']);

        // The endpoint documents a `result` filter by judgment *name*, which
        // upstream cannot express — indices differ per project — so it is applied
        // after decoding. It therefore narrows the page, not the total.
        if (filled($result) && ! $onlyEproxies) {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => $r['result'] === $result));
        }

        return self::envelope(
            array_slice(self::mergeByDay($rows, $eproxies), $offset - $start, $perPage),
            $reports['total'] + count($eproxies),
            $page,
            $perPage,
        );
    }

    /**
     * A project's 전자위임, shaped as activity reports, newest first.
     *
     * They are not rows in `submission` — eSignon records them on the roster row
     * — so they carry no id of their own; `source` and `shareholder_id` identify
     * them. The signer's contact is reported only as present.
     *
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    private static function eproxies(int $projectId, array $params): array
    {
        $filters = [];
        $bounds = [];

        // The completion time is text ("2026-09-24 02:13:08"), so the bounds are
        // compared as strings: from the start of `from` to before the day after `to`.
        if (filled($params['from'] ?? null)) {
            $bounds[] = 'api_recipient_completion_date.gte.'.Carbon::parse($params['from'])->toDateString();
        }

        if (filled($params['to'] ?? null)) {
            $bounds[] = 'api_recipient_completion_date.lt.'.Carbon::parse($params['to'])->addDay()->toDateString();
        }

        if ($bounds !== []) {
            $filters['and'] = '('.implode(',', $bounds).')';
        }

        if (filled($params['worker_id'] ?? null)) {
            $filters['user'] = self::workerNameFilter((string) $params['worker_id']);
        }

        return array_map(static fn (array $row): array => [
            'id' => null,
            'source' => 'esignon',
            'date' => self::date($row['api_recipient_completion_date']),
            'project_id' => (int) $row['project_id'],
            'shareholder_id' => (int) $row['id'],
            // The roster names its workers; it holds no profile uuid to report.
            'worker_id' => null,
            'worker_name' => array_values(array_filter($row['user'] ?? []))[0] ?? null,
            'result' => self::ESIGNON_RESULT,
            'result_color' => 'green',
            'is_proxy' => true,
            'has_contact' => Pii::hasContact($row['api_recipient_contact']),
            'privacy_consent' => false,
            'file_count' => 0,
            'note' => null,
        ], self::shareholderRepository()->eproxiesForProject($projectId, $filters));
    }

    /**
     * Interleave newest-first e-proxies into newest-first reports without
     * re-sorting the reports, whose upstream order is kept as-is.
     *
     * An e-proxy goes ahead of a report only when its day is later, so on a
     * shared day the reports come first, and it never passes an undated report
     * (Postgres puts those first in a descending sort). submissions() computes
     * page offsets on the assumption that this rule is monotone — keep it so.
     *
     * @param  list<array<string, mixed>>  $reports
     * @param  list<array<string, mixed>>  $eproxies
     * @return list<array<string, mixed>>
     */
    private static function mergeByDay(array $reports, array $eproxies): array
    {
        $merged = [];
        $next = 0;

        foreach ($reports as $report) {
            while (isset($eproxies[$next]) && $report['date'] !== null && $eproxies[$next]['date'] > $report['date']) {
                $merged[] = $eproxies[$next++];
            }

            $merged[] = $report;
        }

        return [...$merged, ...array_slice($eproxies, $next)];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null null when the project does not exist
     */
    public static function activityData(int $projectId, array $params): ?array
    {
        $row = self::projectRepository()->find($projectId);

        if ($row === null) {
            return null;
        }

        $results = ResultSet::fromColumn($row['results'] ?? null);

        // Accepted as a comma list (days=a,b) or a repeated param (days[]=a&days[]=b).
        $days = $params['days'] ?? null;

        if (is_string($days)) {
            $days = array_values(array_filter(array_map('trim', explode(',', $days))));
        }

        $days = is_array($days) && $days !== [] ? $days : null;

        $submissions = self::submissionRepository()->forProject($projectId);

        $allDays = array_values(array_unique(array_filter(array_map(
            static fn (array $s): ?string => self::date($s['date'] ?? null),
            $submissions,
        ))));
        sort($allDays);

        if ($days !== null) {
            $wanted = array_flip($days);
            $submissions = array_values(array_filter(
                $submissions,
                static fn (array $s): bool => isset($wanted[self::date($s['date'] ?? null) ?? '']),
            ));
        }

        $includedDays = array_values(array_unique(array_filter(array_map(
            static fn (array $s): ?string => self::date($s['date'] ?? null),
            $submissions,
        ))));
        sort($includedDays);

        $workers = [];

        foreach ($submissions as $submission) {
            // Real profile uuid where present; the denormalized name keeps
            // orphaned legacy rows grouped sensibly.
            $key = $submission['user_id'] ?? 'n:'.($submission['user_name'] ?? '');

            $workers[$key] ??= [
                'name' => $submission['user_name'] ?? '—',
                'total_reports' => 0,
                'days' => [],
                // Ascending order means a later report for the same shareholder
                // overwrites an earlier one, leaving each shareholder's latest.
                'latest' => [],
            ];

            $workers[$key]['total_reports']++;
            $day = self::date($submission['date'] ?? null);

            if ($day !== null) {
                $workers[$key]['days'][$day] = true;
            }

            if (($shareholderId = $submission['shareholder_id'] ?? null) !== null) {
                $workers[$key]['latest'][(int) $shareholderId] = $submission['result'] ?? null;
            }
        }

        $workerRows = array_values(array_map(static function (array $worker) use ($results): array {
            $proxies = 0;

            foreach ($worker['latest'] as $resultIndex) {
                if ($results->resolve($resultIndex)?->isProxy()) {
                    $proxies++;
                }
            }

            return [
                'name' => $worker['name'],
                'total_reports' => $worker['total_reports'],
                'shareholders_contacted' => count($worker['latest']),
                'proxy_shareholders' => $proxies,
                'days_worked' => count($worker['days']),
            ];
        }, $workers));

        usort($workerRows, static fn (array $a, array $b): int => $b['total_reports'] <=> $a['total_reports']);

        return [
            'project_id' => (int) $row['id'],
            'project_title' => $row['title'],
            'is_empty' => $submissions === [],
            'all_activity_days' => $allDays,
            'days_included' => $includedDays,
            'summary' => [
                'total_reports' => count($submissions),
                'workers' => count($workerRows),
                'days' => count($includedDays),
            ],
            'workers' => $workerRows,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function receipts(array $params): array
    {
        $filters = [];

        if (filled($params['worker_id'] ?? null)) {
            $filters['user_id'] = 'eq.'.$params['worker_id'];
        }

        if (filled($params['category'] ?? null)) {
            $filters['usage_history'] = 'eq.'.$params['category'];
        }

        if (filled($params['from'] ?? null)) {
            $filters['date'] = 'gte.'.$params['from'];
        }

        if (filled($params['to'] ?? null)) {
            $filters['and'] = '(date.lte.'.$params['to'].'T23:59:59)';
        }

        [$page, $perPage] = self::pageParams($params);
        $result = self::receiptRepository()->paginate($filters, $page, $perPage);

        $rows = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'date' => self::date($row['date'] ?? null),
            'worker_id' => $row['user_id'] ?? null,
            'worker_name' => $row['user_name'] ?? null,
            'category' => $row['usage_history'] ?? null,
            'vendor' => $row['where_used'] ?? null,
            'amount' => Num::decimal($row['amount'] ?? null),
            // Upstream's meaning is unconfirmed — it is NOT a delete flag — so it
            // is reported as-is and nothing is filtered on it.
            'status' => $row['status'] ?? null,
            'has_attachment' => filled($row['attachments'] ?? null),
        ], $result['rows']);

        // Totals cover the whole filtered set, not just the current page.
        $all = self::receiptRepository()->allMatching($filters);
        $byCategory = [];

        foreach ($all as $row) {
            $category = $row['usage_history'] ?? null;
            $byCategory[$category] ??= ['category' => $category, 'count' => 0, 'amount' => 0.0];
            $byCategory[$category]['count']++;
            $byCategory[$category]['amount'] += Num::decimal($row['amount'] ?? null) ?? 0.0;
        }

        usort($byCategory, static fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        return [
            ...self::envelope($rows, $result['total'], $page, $perPage),
            'total_amount' => array_sum(array_column($byCategory, 'amount')),
            'by_category' => array_values($byCategory),
        ];
    }

    /**
     * The 판단 결과 현황: shares behind each judgment, grouped into colour bands
     * and measured against the target.
     *
     * The sums come from a Postgres function rather than PHP — adding up shares
     * across a 1.4M-row table here would mean fetching the whole roster.
     *
     * @return array<string, mixed>
     */
    private static function judgmentTally(int $projectId, ResultSet $results, ?int $target): array
    {
        $buckets = [];

        foreach (self::projectRepository()->tally($projectId) as $bucket) {
            $buckets[(string) ($bucket['bucket'] ?? '')] = [
                'count' => (int) ($bucket['cnt'] ?? 0),
                'shares' => (int) ($bucket['total_shares'] ?? 0),
            ];
        }

        $groups = [];

        foreach ($results->judgments as $judgment) {
            $data = $buckets[(string) $judgment->index] ?? null;

            if ($data === null || $data['shares'] === 0) {
                continue; // Only populated 판단 are listed.
            }

            $row = [
                'name' => $judgment->name,
                'color' => $judgment->color,
                'shares' => $data['shares'],
                'percent_of_target' => $target > 0 ? $data['shares'] / $target * 100 : null,
            ];

            // Consecutive judgments sharing a colour merge into one band.
            $last = array_key_last($groups);

            if ($last !== null && $groups[$last]['color'] === $judgment->color) {
                $groups[$last]['rows'][] = $row;
                $groups[$last]['total_shares'] += $data['shares'];
            } else {
                $groups[] = ['color' => $judgment->color, 'rows' => [$row], 'total_shares' => $data['shares']];
            }
        }

        foreach ($groups as &$group) {
            $group['total_percent_of_target'] = $target > 0 ? $group['total_shares'] / $target * 100 : null;
        }
        unset($group);

        return [
            'has_data' => $groups !== [],
            'groups' => $groups,
            // Electronic proxies (전자위임) are counted upstream in their own
            // bucket rather than against a judgment index.
            'electronic_proxy' => $buckets['__eproxy__'] ?? null,
            'unjudged' => $buckets['__null__'] ?? null,
        ];
    }

    /**
     * Decode the judgment list for several projects at once.
     *
     * @param  list<mixed>  $projectIds
     * @return array<int, ResultSet>
     */
    private static function resultSetsFor(array $projectIds): array
    {
        $sets = [];

        foreach (array_unique(array_filter($projectIds)) as $projectId) {
            $row = self::projectRepository()->find((int) $projectId);
            $sets[(int) $projectId] = ResultSet::fromColumn($row['results'] ?? null);
        }

        return $sets;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private static function envelope(array $rows, int $total, int $page, int $perPage): array
    {
        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $perPage > 0 ? (int) max(1, ceil($total / $perPage)) : 1,
            'data' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{int, int}
     */
    private static function pageParams(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = (int) ($params['per_page'] ?? self::PER_PAGE_DEFAULT);
        $perPage = max(1, min($perPage ?: self::PER_PAGE_DEFAULT, self::PER_PAGE_MAX));

        return [$page, $perPage];
    }

    /**
     * A timestamptz reduced to its date, which is all any endpoint reports.
     */
    private static function date(?string $timestamp): ?string
    {
        if (blank($timestamp)) {
            return null;
        }

        return substr($timestamp, 0, 10);
    }

    private static function daysUntil(?string $timestamp): ?int
    {
        if (blank($timestamp)) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays(
            Carbon::parse($timestamp)->startOfDay(),
            absolute: false,
        );
    }

    /**
     * The birth year, derived from the 실명번호 rather than `date_of_birth`.
     *
     * That column cannot be trusted: upstream stores JavaScript epoch
     * milliseconds as text ("470847600000"), and inconsistently — some rows hold
     * a day count instead. The RRN carries the birth date and a century digit,
     * which {@see KoreanRegistration} already resolves correctly.
     *
     * A year, never a date: a full date of birth is an identifier, the year is a
     * demographic.
     */
    private static function birthYear(?string $registration): ?int
    {
        if (blank($registration)) {
            return null;
        }

        return Pii::birthYear(KoreanRegistration::from($registration)->dateOfBirth());
    }

    /**
     * A PostgREST filter on a roster row's `user` for one worker. Roster
     * assignment is by worker *name*, so the uuid is translated first; an unknown
     * uuid matches nothing rather than everything.
     */
    private static function workerNameFilter(string $workerId): string
    {
        $name = self::profileRepository()->nameFor($workerId);

        return $name === null ? 'cs.{__no_such_worker__}' : 'cs.{'.$name.'}';
    }

    private static function boolish(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true'], true);
    }

    private static function projectRepository(): ProjectRepository
    {
        return app(ProjectRepository::class);
    }

    private static function shareholderRepository(): ShareholderRepository
    {
        return app(ShareholderRepository::class);
    }

    private static function submissionRepository(): SubmissionRepository
    {
        return app(SubmissionRepository::class);
    }

    private static function receiptRepository(): ReceiptRepository
    {
        return app(ReceiptRepository::class);
    }

    private static function profileRepository(): ProfileRepository
    {
        return app(ProfileRepository::class);
    }
}
