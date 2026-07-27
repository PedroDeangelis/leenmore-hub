<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The `search` half of the search/fetch pair ChatGPT's deep research mode looks
 * for. It doubles as the "I only have a name" entry point for any client.
 *
 * @see FetchTool for the other half.
 */
#[Name('search')]
#[Title('Search')]
#[Description('Search projects, shareholders and staff by name, returning {id, title, url} records. Pass an id to the fetch tool for the full record. Prefer the dedicated list tools when you already know what you are looking for — they filter far better.')]
#[IsReadOnly]
#[IsIdempotent]
class SearchTool extends Tool
{
    /** Applied per type, so one crowded type cannot crowd the others out. */
    private const LIMIT = 20;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:100'],
        ]);

        $term = '%'.$validated['query'].'%';

        $results = $this->projects($term)
            ->concat($this->shareholders($term))
            ->concat($this->users($term))
            ->values();

        return Response::structured([
            'results' => $results->all(),
            'total' => $results->count(),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function projects(string $term): Collection
    {
        return Project::query()
            ->where('title', 'like', $term)
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Project $project): array => [
                'id' => 'project:'.$project->id,
                'type' => 'project',
                'title' => $project->title,
                'url' => route('projects.show', $project),
                'summary' => 'Project ('.$project->status->value.'), meeting '.($project->end_date?->toDateString() ?? 'unscheduled'),
            ]);
    }

    /**
     * Shareholders are people, not pages, so each links to their activity record on
     * the most recent project they appear in. The `whereIn` against Project applies
     * that model's global scope, keeping deleted projects out of the link.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function shareholders(string $term): Collection
    {
        $latestAssignment = fn (string $column) => ProjectShareholder::query()
            ->select($column)
            ->whereColumn('project_shareholders.shareholder_id', 'shareholders.id')
            ->whereIn('project_id', Project::query()->select('id'))
            ->latest('id')
            ->limit(1);

        return Shareholder::query()
            ->select('shareholders.*')
            ->addSelect([
                'latest_project_id' => $latestAssignment('project_id'),
                'latest_assignment_id' => $latestAssignment('id'),
            ])
            ->where('name', 'like', $term)
            ->withCount('assignments')
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Shareholder $shareholder): array => [
                'id' => 'shareholder:'.$shareholder->id,
                'type' => 'shareholder',
                'title' => $shareholder->name,
                'url' => $shareholder->latest_project_id === null
                    ? route('projects.index')
                    : route('activity.report', [$shareholder->latest_project_id, $shareholder->latest_assignment_id]),
                'summary' => 'Shareholder ('.($shareholder->person_type?->value ?? 'unknown').'), on '.$shareholder->assignments_count.' project(s)',
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function users(string $term): Collection
    {
        return User::query()
            ->where('name', 'like', $term)
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (User $user): array => [
                'id' => 'user:'.$user->id,
                'type' => 'user',
                'title' => $user->name,
                'url' => route('users.show', $user),
                'summary' => 'Account ('.$user->role->value.'), '.($user->deactivated_at === null ? 'active' : 'deactivated'),
            ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->required()
                ->max(100)
                ->description('Free text matched against project titles, shareholder names and account names.'),
        ];
    }
}
