<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Receipt;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The read layer behind the REST API (app/Http/Controllers/Api). One method per
 * endpoint, each taking already-validated params and returning a plain array.
 *
 * Both the controllers and the in-app "Try it" tester call these, so what a
 * developer sees documented and what the endpoint returns cannot drift. Every
 * method routes personal data through {@see Pii} and reuses the same
 * {@see JudgmentTally} / {@see ActivityData} the dashboard and exports use, so
 * the API can never disagree with the app's own screens.
 *
 * This mirrors the MCP tools in app/Mcp/Tools deliberately: same masking, same
 * shapes, different transport.
 */
final class ApiData
{
    private const PER_PAGE_DEFAULT = 50;

    private const PER_PAGE_MAX = 200;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function projects(array $params): array
    {
        $query = Project::query()
            ->withCount(['shareholders', 'submissions'])
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        match ($params['status'] ?? null) {
            'active' => $query->active(),
            null, '' => null,
            default => $query->where('status', $params['status']),
        };

        if (filled($params['search'] ?? null)) {
            $query->where('title', 'like', '%'.$params['search'].'%');
        }

        if (filled($params['from'] ?? null)) {
            $query->whereDate('start_date', '>=', $params['from']);
        }

        if (filled($params['to'] ?? null)) {
            $query->whereDate('start_date', '<=', $params['to']);
        }

        return self::paginate($query, $params, fn (Project $project): array => [
            'id' => $project->id,
            'title' => $project->title,
            'status' => $project->status->value,
            'start_date' => $project->start_date?->toDateString(),
            'meeting_date' => $project->end_date?->toDateString(),
            'days_until_meeting' => $project->meetingCountdown()?->daysLeft,
            'shares_issued' => $project->shares_issued,
            'shares_target' => $project->shares_target,
            'shareholder_count' => $project->shareholders_count,
            'submission_count' => $project->submissions_count,
        ]);
    }

    /**
     * @return array<string, mixed>|null null when the project does not exist / is deleted
     */
    public static function project(int $projectId): ?array
    {
        $project = Project::query()
            ->withCount(['shareholders', 'submissions'])
            ->with('results')
            ->find($projectId);

        if ($project === null) {
            return null;
        }

        $judged = $project->shareholders()->reorder()->whereNotNull('result_id')->count();
        $tally = JudgmentTally::for($project);

        return [
            'id' => $project->id,
            'title' => $project->title,
            'status' => $project->status->value,
            'message' => $project->message,
            'start_date' => $project->start_date?->toDateString(),
            'meeting_date' => $project->end_date?->toDateString(),
            'days_until_meeting' => $project->meetingCountdown()?->daysLeft,
            'shares_issued' => $project->shares_issued,
            'shares_target' => $project->shares_target,
            'shareholder_count' => $project->shareholders_count,
            'judged_shareholder_count' => $judged,
            'unjudged_shareholder_count' => $project->shareholders_count - $judged,
            'submission_count' => $project->submissions_count,
            'results' => $project->results->map(fn ($result): array => [
                'id' => $result->id,
                'name' => $result->name,
                'color' => $result->color->value,
                'is_proxy' => $result->color->value === 'green',
                'contact_required' => $result->contact_required,
                'attachment_required' => $result->attachment_required,
            ])->all(),
            'judgment_tally' => [
                'has_data' => $tally->hasData(),
                'groups' => array_map(fn (array $group): array => [
                    'color' => $group['color']->value,
                    'total_shares' => $group['totalShares'],
                    'total_percent_of_target' => $group['totalPercent'],
                    'rows' => array_map(fn (array $row): array => [
                        'name' => $row['name'],
                        'color' => $row['color']->value,
                        'shares' => $row['shares'],
                        'percent_of_target' => $row['percent'],
                    ], $group['rows']),
                ], $tally->groups),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function shareholders(int $projectId, array $params): array
    {
        $query = ProjectShareholder::query()
            ->where('project_id', $projectId)
            ->with(['shareholder', 'result', 'workers:id,name'])
            ->orderBy('no');

        if (filled($params['result'] ?? null)) {
            $query->whereHas('result', fn (Builder $q) => $q->where('name', $params['result']));
        }

        // 위임 is any green 판단, never a single label — same rule as ActivityData.
        if (self::boolish($params['proxy_only'] ?? null)) {
            $query->whereHas('result', fn (Builder $q) => $q->where('color', 'green'));
        }

        if (array_key_exists('judged', $params) && $params['judged'] !== null && $params['judged'] !== '') {
            self::boolish($params['judged'])
                ? $query->whereNotNull('result_id')
                : $query->whereNull('result_id');
        }

        if (filled($params['worker_id'] ?? null)) {
            $query->whereHas('workers', fn (Builder $q) => $q->where('users.id', $params['worker_id']));
        }

        if (filled($params['search'] ?? null)) {
            $query->whereHas('shareholder', fn (Builder $q) => $q->where('name', 'like', '%'.$params['search'].'%'));
        }

        return self::paginate($query, $params, function (ProjectShareholder $assignment): array {
            $person = $assignment->shareholder;

            return [
                'id' => $assignment->id,
                'no' => $assignment->no,
                'shareholder_id' => $person?->id,
                'name' => $person?->name,
                'person_type' => $person?->person_type?->value,
                'registration_masked' => Pii::registration($person?->registration),
                'birth_year' => Pii::birthYear($person?->date_of_birth),
                'region' => Pii::address($assignment->effective_address),
                'has_contact' => Pii::hasContact($assignment->effective_contact, $assignment->contact_info_2),
                'shares' => $assignment->shares,
                'shares_total' => $assignment->shares_total,
                'result' => $assignment->result?->name,
                'result_color' => $assignment->result?->color->value,
                'is_proxy' => $assignment->result?->color->value === 'green',
                'electronic_voting' => $assignment->electronic_voting,
                'workers' => $assignment->workers->pluck('name')->all(),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function submissions(array $params): array
    {
        $query = Submission::query()
            ->with(['project:id,title', 'projectShareholder.shareholder:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        foreach (['project_id' => 'project_id', 'worker_id' => 'user_id'] as $param => $column) {
            if (filled($params[$param] ?? null)) {
                $query->where($column, $params[$param]);
            }
        }

        if (filled($params['result'] ?? null)) {
            $query->where('result', $params['result']);
        }

        if (filled($params['from'] ?? null)) {
            $query->whereDate('date', '>=', $params['from']);
        }

        if (filled($params['to'] ?? null)) {
            $query->whereDate('date', '<=', $params['to']);
        }

        // The free-text `note` is deliberately absent — workers type phone numbers
        // into it, so it is never returned in a list.
        return self::paginate($query, $params, fn (Submission $submission): array => [
            'id' => $submission->id,
            'date' => $submission->date?->toDateString(),
            'project_id' => $submission->project_id,
            'project_title' => $submission->project?->title,
            'shareholder_name' => $submission->projectShareholder?->shareholder?->name,
            'project_shareholder_id' => $submission->project_shareholder_id,
            'worker_id' => $submission->user_id,
            'worker_name' => $submission->user_name,
            'result' => $submission->result,
            'has_contact' => Pii::hasContact($submission->contact),
            'privacy_consent' => $submission->privacy_consent,
            'file_count' => count($submission->files ?? []),
            'entered_by_office' => $submission->created_by_user_id !== null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null null when the project does not exist / is deleted
     */
    public static function activityData(int $projectId, array $params): ?array
    {
        $project = Project::query()->with('results')->find($projectId);

        if ($project === null) {
            return null;
        }

        // Accepted as a comma list (days=a,b) or a repeated param (days[]=a&days[]=b).
        $days = $params['days'] ?? null;

        if (is_string($days)) {
            $days = array_values(array_filter(array_map('trim', explode(',', $days))));
        }

        $data = ActivityData::for($project, is_array($days) && $days !== [] ? $days : null);

        return [
            'project_id' => $project->id,
            'project_title' => $project->title,
            'is_empty' => $data->isEmpty(),
            'all_activity_days' => $data->allDays(),
            'days_included' => $data->days(),
            'summary' => $data->summary(),
            'workers' => array_map(fn (array $worker): array => [
                'name' => $worker['name'],
                'total_reports' => $worker['totalReports'],
                'shareholders_assigned' => $worker['shareholderCount'],
                'shares_assigned' => $worker['assignedShares'],
                'shares_contacted' => $worker['sharesContacted'],
                'proxy_shareholders' => $worker['proxyShareholders'],
                'proxy_shares' => $worker['proxyShares'],
                'days_worked' => $worker['daysWorked'],
            ], $data->workers()),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function receipts(array $params): array
    {
        $filtered = fn (): Builder => Receipt::query()
            ->when(filled($params['worker_id'] ?? null), fn (Builder $q) => $q->where('user_id', $params['worker_id']))
            ->when(filled($params['category'] ?? null), fn (Builder $q) => $q->where('category_name', $params['category']))
            ->when(filled($params['from'] ?? null), fn (Builder $q) => $q->whereDate('date', '>=', $params['from']))
            ->when(filled($params['to'] ?? null), fn (Builder $q) => $q->whereDate('date', '<=', $params['to']));

        $page = self::paginate(
            $filtered()->orderByDesc('date')->orderByDesc('id'),
            $params,
            fn (Receipt $receipt): array => [
                'id' => $receipt->id,
                'date' => $receipt->date?->toDateString(),
                'worker_id' => $receipt->user_id,
                'worker_name' => $receipt->user_name,
                'category' => $receipt->category_name,
                'vendor' => $receipt->vendor,
                'amount' => $receipt->amount,
                'has_attachment' => filled($receipt->attachment),
            ],
        );

        // Totals cover the whole filtered set, not just the current page.
        return [
            ...$page,
            'total_amount' => (int) $filtered()->sum('amount'),
            'by_category' => $filtered()
                ->selectRaw('category_name, COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')
                ->groupBy('category_name')
                ->orderByDesc('amount')
                ->get()
                ->map(fn ($row): array => [
                    'category' => $row->category_name,
                    'count' => (int) $row->count,
                    'amount' => (int) $row->amount,
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function users(array $params): array
    {
        $query = User::query()->orderBy('name');

        if (filled($params['role'] ?? null)) {
            $query->where('role', $params['role']);
        }

        if (array_key_exists('active', $params) && $params['active'] !== null && $params['active'] !== '') {
            self::boolish($params['active'])
                ? $query->whereNull('deactivated_at')
                : $query->whereNotNull('deactivated_at');
        }

        if (filled($params['search'] ?? null)) {
            $query->where('name', 'like', '%'.$params['search'].'%');
        }

        // Staff email and phone are personal contact details — only presence is reported.
        return self::paginate($query, $params, fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role->value,
            'is_active' => $user->deactivated_at === null,
            'deactivated_at' => $user->deactivated_at?->toDateString(),
            'has_phone' => filled($user->phone),
            'locale' => $user->locale,
        ]);
    }

    /**
     * Run a query through the paginator and map each row, capped so a broad
     * request cannot dump the whole table. Always reports `total`.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $params
     * @param  \Closure(mixed): array<string, mixed>  $map
     * @return array<string, mixed>
     */
    private static function paginate(Builder $query, array $params, \Closure $map): array
    {
        /** @var LengthAwarePaginator<int, Model> $page */
        $page = $query->paginate(
            perPage: min(max((int) ($params['per_page'] ?? self::PER_PAGE_DEFAULT), 1), self::PER_PAGE_MAX),
            page: max((int) ($params['page'] ?? 1), 1),
        );

        return [
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'last_page' => $page->lastPage(),
            'data' => array_map($map, $page->items()),
        ];
    }

    /**
     * Accept true/false, "true"/"false", 1/0, "1"/"0" alike — query strings and
     * the in-app tester both arrive as strings.
     */
    private static function boolish(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
