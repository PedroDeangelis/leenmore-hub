<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use App\Support\ActivityData;
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

#[Name('activity-data')]
#[Title('Per-worker activity data')]
#[Description('활동 데이터: per-worker performance on a project — contacts made, proxies (위임) obtained, shares covered and days worked. Use this for any question about how workers are performing rather than counting activity reports yourself.')]
#[IsReadOnly]
#[IsIdempotent]
class ActivityDataTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'days' => ['sometimes', 'array'],
            'days.*' => ['date_format:Y-m-d'],
        ]);

        $project = Project::query()->with('results')->find($validated['project_id']);

        if ($project === null) {
            return Response::error('No project with id '.$validated['project_id'].'. It may be deleted.');
        }

        // Same computation as the 활동 데이터 page and its Excel export, so these
        // figures always agree with what staff see in the app.
        $data = ActivityData::for($project, $validated['days'] ?? null);

        return Response::structured([
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
            'note' => 'total_reports counts every report including repeat visits to the same shareholder; share and proxy figures dedupe to each shareholder\'s latest report. The two differing is expected.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('The project to analyse, from list-projects.'),
            'days' => $schema->array()
                ->items($schema->string()->format('date'))
                ->description('Restrict to these activity days (YYYY-MM-DD). Omit for all days; the response lists every available day in all_activity_days.'),
        ];
    }
}
