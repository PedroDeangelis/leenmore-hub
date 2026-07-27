<?php

namespace App\Mcp\Tools;

use App\Enums\ResultColor;
use App\Models\Project;
use App\Models\ProjectResult;
use App\Support\JudgmentTally;
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

#[Name('get-project')]
#[Title('Get one project')]
#[Description('One project in full: its 판단 (judgment) definitions and the 판단 결과 현황 tally — shares behind each judgment, grouped into colour bands and measured against the share target.')]
#[IsReadOnly]
#[IsIdempotent]
class GetProjectTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
        ]);

        $project = Project::query()
            ->withCount(['shareholders', 'submissions'])
            ->with('results')
            ->find($validated['project_id']);

        if ($project === null) {
            return Response::error('No project with id '.$validated['project_id'].'. It may be deleted.');
        }

        $judged = $project->shareholders()->reorder()->whereNotNull('result_id')->count();

        return Response::structured([
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
            'assigned_worker_count' => $project->assignedWorkers()->count(),
            'results' => $project->results->map($this->result(...))->all(),
            'judgment_tally' => $this->tally($project),
        ]);
    }

    /**
     * A 판단 definition. `is_proxy` spells out the green-colour rule so the model
     * does not have to re-derive it from the colour on every question.
     *
     * @return array<string, mixed>
     */
    private function result(ProjectResult $result): array
    {
        return [
            'id' => $result->id,
            'name' => $result->name,
            'color' => $result->color->value,
            'is_proxy' => $result->color === ResultColor::Green,
            'contact_required' => $result->contact_required,
            'attachment_required' => $result->attachment_required,
        ];
    }

    /**
     * The 판단 결과 현황 bands, straight from the same JudgmentTally the project
     * dashboard and the Excel export use — so these numbers cannot drift from the UI.
     *
     * @return array<string, mixed>
     */
    private function tally(Project $project): array
    {
        $tally = JudgmentTally::for($project);

        return [
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
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('The project id, from list-projects.'),
        ];
    }
}
