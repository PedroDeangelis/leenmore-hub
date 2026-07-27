<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\Paginates;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-projects')]
#[Title('List projects')]
#[Description('List shareholder-meeting projects with their share targets, roster size and days until the meeting. Start here to find a project id for the other tools.')]
#[IsReadOnly]
#[IsIdempotent]
class ListProjectsTool extends Tool
{
    use Paginates;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:draft,publish,archived,active'],
            'search' => ['sometimes', 'string', 'max:100'],
            ...$this->pageRules(),
        ]);

        $query = Project::query()
            ->withCount(['shareholders', 'submissions'])
            ->orderByDesc('id');

        // "active" is the draft+publish pair the app itself treats as live. Deleted
        // projects are excluded by the model's global scope and unreachable here.
        match ($validated['status'] ?? null) {
            'active' => $query->active(),
            null => null,
            default => $query->where('status', $validated['status']),
        };

        if (filled($validated['search'] ?? null)) {
            $query->where('title', 'like', '%'.$validated['search'].'%');
        }

        return Response::structured($this->paginated($query, $validated, $this->row(...)));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Project $project): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(['draft', 'publish', 'archived', 'active'])
                ->description('Filter by lifecycle state. "active" means draft or publish — the projects currently being worked.'),
            'search' => $schema->string()
                ->max(100)
                ->description('Match against the project title.'),
            ...$this->pageSchema($schema),
        ];
    }
}
