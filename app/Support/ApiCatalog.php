<?php

namespace App\Support;

use Closure;

/**
 * The single source of truth for the REST API's endpoints. Drives three things
 * so they can never fall out of step: the sidebar submenu, the per-endpoint docs
 * pages, and the in-app "Try it" tester (which runs the endpoint's `run` handler
 * against {@see ApiData}).
 *
 * Endpoint reference text is intentionally English — it is developer-facing API
 * documentation. The surrounding UI chrome stays translated via __().
 */
final class ApiCatalog
{
    public const BASE = '/api/v1';

    /**
     * Every endpoint, keyed by slug, in sidebar order.
     *
     * @return array<string, array{
     *     method: string, path: string, title: string, summary: string,
     *     params: list<array{name: string, in: string, type: string, required: bool, description: string, example?: string, options?: list<string>, input?: string, rules: list<string>}>,
     *     run: Closure(array<string, mixed>): (array<string, mixed>|null)
     * }>
     */
    public static function all(): array
    {
        $page = [
            ['name' => 'page', 'in' => 'query', 'type' => 'integer', 'required' => false,
                'description' => 'Which page of results to return (default 1).', 'example' => '1',
                'rules' => ['sometimes', 'integer', 'min:1']],
            ['name' => 'per_page', 'in' => 'query', 'type' => 'integer', 'required' => false,
                'description' => 'Rows per page, 1–200 (default 50).', 'example' => '50',
                'rules' => ['sometimes', 'integer', 'min:1', 'max:200']],
        ];

        return [
            'projects' => [
                'method' => 'GET',
                'path' => self::BASE.'/projects',
                'title' => 'List projects',
                'summary' => 'Shareholder-meeting projects with share targets, roster size and days until the meeting. Start here to find a project id for the other endpoints.',
                'params' => [
                    ['name' => 'status', 'in' => 'query', 'type' => 'string', 'required' => false,
                        'description' => 'Filter by lifecycle state. "active" means draft or publish.',
                        'options' => ['draft', 'publish', 'archived', 'active'],
                        'rules' => ['sometimes', 'string', 'in:draft,publish,archived,active']],
                    ['name' => 'search', 'in' => 'query', 'type' => 'string', 'required' => false,
                        'description' => 'Match against the project title.', 'example' => '',
                        'rules' => ['sometimes', 'string', 'max:100']],
                    ['name' => 'from', 'in' => 'query', 'type' => 'date', 'required' => false,
                        'description' => 'Earliest start date, YYYY-MM-DD.', 'example' => '2026-01-01',
                        'rules' => ['sometimes', 'date']],
                    ['name' => 'to', 'in' => 'query', 'type' => 'date', 'required' => false,
                        'description' => 'Latest start date, YYYY-MM-DD.', 'example' => '2026-12-31',
                        'rules' => ['sometimes', 'date']],
                    ...$page,
                ],
                'run' => fn (array $p): array => ApiData::projects($p),
            ],
            'project' => [
                'method' => 'GET',
                'path' => self::BASE.'/projects/{project}',
                'title' => 'Get one project',
                'summary' => 'One project in full: its 판단 (judgment) definitions and the 판단 결과 현황 tally — shares behind each judgment, grouped by colour band and measured against the share target.',
                'params' => [
                    ['name' => 'project', 'in' => 'path', 'type' => 'integer', 'required' => true,
                        'description' => 'The project id, from the projects list.', 'example' => '1',
                        'rules' => ['required', 'integer']],
                ],
                'run' => fn (array $p): ?array => ApiData::project((int) $p['project']),
            ],
            'shareholders' => [
                'method' => 'GET',
                'path' => self::BASE.'/projects/{project}/shareholders',
                'title' => 'List a project roster',
                'summary' => "A project's shareholder roster (주주명부): shares held, current 판단 and assigned workers. 실명번호 is masked and contact numbers are never returned.",
                'params' => [
                    ['name' => 'project', 'in' => 'path', 'type' => 'integer', 'required' => true,
                        'description' => 'The project whose roster to list.', 'example' => '1',
                        'rules' => ['required', 'integer']],
                    ['name' => 'result', 'in' => 'query', 'type' => 'string', 'required' => false,
                        'description' => 'Exact 판단 name to filter by. Use proxy_only for 위임.',
                        'rules' => ['sometimes', 'string', 'max:100']],
                    ['name' => 'proxy_only', 'in' => 'query', 'type' => 'boolean', 'required' => false,
                        'description' => 'true = only shareholders whose current 판단 is a 위임 (any green judgment).',
                        'options' => ['true', 'false'], 'rules' => ['sometimes', 'in:true,false,1,0']],
                    ['name' => 'judged', 'in' => 'query', 'type' => 'boolean', 'required' => false,
                        'description' => 'true = only judged; false = only outstanding.',
                        'options' => ['true', 'false'], 'rules' => ['sometimes', 'in:true,false,1,0']],
                    ['name' => 'worker_id', 'in' => 'query', 'type' => 'integer', 'required' => false,
                        'description' => 'Only shareholders assigned to this worker (from the users list).',
                        'rules' => ['sometimes', 'integer']],
                    ['name' => 'search', 'in' => 'query', 'type' => 'string', 'required' => false,
                        'description' => "Match against the shareholder's name.",
                        'rules' => ['sometimes', 'string', 'max:100']],
                    ...$page,
                ],
                'run' => fn (array $p): ?array => ApiData::shareholders((int) $p['project'], $p),
            ],
            'submissions' => [
                'method' => 'GET',
                'path' => self::BASE.'/submissions',
                'title' => 'List activity reports',
                'summary' => 'Activity reports (활동 보고): the per-visit history of what a worker recorded for a shareholder. Free-text notes are excluded.',
                'params' => [
                    ['name' => 'project_id', 'in' => 'query', 'type' => 'integer', 'required' => false,
                        'description' => 'Only reports for this project.', 'example' => '1',
                        'rules' => ['sometimes', 'integer']],
                    ['name' => 'worker_id', 'in' => 'query', 'type' => 'integer', 'required' => false,
                        'description' => 'Only reports filed by this worker.',
                        'rules' => ['sometimes', 'integer']],
                    ['name' => 'result', 'in' => 'query', 'type' => 'string', 'required' => false,
                        'description' => 'Exact 판단 name recorded on the report.',
                        'rules' => ['sometimes', 'string', 'max:100']],
                    ['name' => 'from', 'in' => 'query', 'type' => 'date', 'required' => false,
                        'description' => 'Earliest activity date, YYYY-MM-DD.', 'example' => '2026-01-01',
                        'rules' => ['sometimes', 'date']],
                    ['name' => 'to', 'in' => 'query', 'type' => 'date', 'required' => false,
                        'description' => 'Latest activity date, YYYY-MM-DD.', 'example' => '2026-12-31',
                        'rules' => ['sometimes', 'date']],
                    ...$page,
                ],
                'run' => fn (array $p): array => ApiData::submissions($p),
            ],
            'activity-data' => [
                'method' => 'GET',
                'path' => self::BASE.'/projects/{project}/activity-data',
                'title' => 'Per-worker activity data',
                'summary' => '활동 데이터: per-worker performance on a project — contacts made, proxies (위임) obtained, shares covered and days worked.',
                'params' => [
                    ['name' => 'project', 'in' => 'path', 'type' => 'integer', 'required' => true,
                        'description' => 'The project to analyse.', 'example' => '1',
                        'rules' => ['required', 'integer']],
                    ['name' => 'days', 'in' => 'query', 'type' => 'string', 'required' => false,
                        'description' => 'Restrict to these activity days, comma-separated (days=2026-03-01,2026-03-02). Repeating days[] also works. Omit for all days.',
                        'example' => '2026-03-01,2026-03-02',
                        'input' => 'date-list',
                        'rules' => ['sometimes']],
                ],
                'run' => fn (array $p): ?array => ApiData::activityData((int) $p['project'], $p),
            ],
            'receipts' => [
                'method' => 'GET',
                'path' => self::BASE.'/receipts',
                'title' => 'List expense receipts',
                'summary' => 'Expense receipts (영수증) submitted by field workers, with the summed total and a per-category breakdown for the filtered set. Amounts are Korean won.',
                'params' => [
                    ['name' => 'worker_id', 'in' => 'query', 'type' => 'integer', 'required' => false,
                        'description' => 'Only receipts submitted by this worker.',
                        'rules' => ['sometimes', 'integer']],
                    ['name' => 'category', 'in' => 'query', 'type' => 'string', 'required' => false,
                        'description' => 'Exact usage category (사용 내역) name.',
                        'rules' => ['sometimes', 'string', 'max:100']],
                    ['name' => 'from', 'in' => 'query', 'type' => 'date', 'required' => false,
                        'description' => 'Earliest receipt date, YYYY-MM-DD.', 'example' => '2026-01-01',
                        'rules' => ['sometimes', 'date']],
                    ['name' => 'to', 'in' => 'query', 'type' => 'date', 'required' => false,
                        'description' => 'Latest receipt date, YYYY-MM-DD.', 'example' => '2026-12-31',
                        'rules' => ['sometimes', 'date']],
                    ...$page,
                ],
                'run' => fn (array $p): array => ApiData::receipts($p),
            ],
            'users' => [
                'method' => 'GET',
                'path' => self::BASE.'/users',
                'title' => 'List staff and workers',
                'summary' => 'Hub accounts: field workers (활동가) and back-office staff, with role and active state. Email and phone are never returned.',
                'params' => [
                    ['name' => 'role', 'in' => 'query', 'type' => 'string', 'required' => false,
                        'description' => 'worker = field worker; admin and office are back-office staff.',
                        'options' => ['admin', 'office', 'worker'],
                        'rules' => ['sometimes', 'string', 'in:admin,office,worker']],
                    ['name' => 'active', 'in' => 'query', 'type' => 'boolean', 'required' => false,
                        'description' => 'true = active accounts only; false = deactivated only.',
                        'options' => ['true', 'false'], 'rules' => ['sometimes', 'in:true,false,1,0']],
                    ['name' => 'search', 'in' => 'query', 'type' => 'string', 'required' => false,
                        'description' => "Match against the account's name.",
                        'rules' => ['sometimes', 'string', 'max:100']],
                    ...$page,
                ],
                'run' => fn (array $p): array => ApiData::users($p),
            ],
        ];
    }

    /**
     * A single endpoint definition, or null for an unknown slug.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    public static function has(string $slug): bool
    {
        return isset(self::all()[$slug]);
    }

    /**
     * Validation rules for an endpoint's query params, keyed by param name.
     * Path params are validated by the controller against the bound model.
     *
     * @return array<string, list<string>>
     */
    public static function queryRules(string $slug): array
    {
        $endpoint = self::find($slug);

        if ($endpoint === null) {
            return [];
        }

        $rules = [];

        foreach ($endpoint['params'] as $param) {
            if ($param['in'] === 'query') {
                $rules[$param['name']] = $param['rules'];
            }
        }

        return $rules;
    }
}
