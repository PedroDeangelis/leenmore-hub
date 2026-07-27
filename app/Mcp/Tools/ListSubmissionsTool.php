<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\Paginates;
use App\Models\Submission;
use App\Support\Pii;
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

#[Name('list-submissions')]
#[Title('List activity reports')]
#[Description('Activity reports (활동 보고) — the per-visit history of what a worker recorded for a shareholder on a given day. Answers "what happened". Free-text notes are excluded; use fetch for a single report if you need one.')]
#[IsReadOnly]
#[IsIdempotent]
class ListSubmissionsTool extends Tool
{
    use Paginates;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => ['sometimes', 'integer', 'exists:projects,id'],
            'worker_id' => ['sometimes', 'integer', 'exists:users,id'],
            'result' => ['sometimes', 'string', 'max:100'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            ...$this->pageRules(),
        ]);

        $query = Submission::query()
            ->with(['project:id,title', 'projectShareholder.shareholder:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        foreach (['project_id' => 'project_id', 'worker_id' => 'user_id'] as $argument => $column) {
            if (filled($validated[$argument] ?? null)) {
                $query->where($column, $validated[$argument]);
            }
        }

        if (filled($validated['result'] ?? null)) {
            $query->where('result', $validated['result']);
        }

        if (filled($validated['from'] ?? null)) {
            $query->whereDate('date', '>=', $validated['from']);
        }

        if (filled($validated['to'] ?? null)) {
            $query->whereDate('date', '<=', $validated['to']);
        }

        return Response::structured($this->paginated($query, $validated, $this->row(...)));
    }

    /**
     * The `note` column is deliberately absent: workers type phone numbers into it,
     * so it is reachable one record at a time through FetchTool and never in bulk.
     *
     * @return array<string, mixed>
     */
    private function row(Submission $submission): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->description('Only reports for this project.'),
            'worker_id' => $schema->integer()
                ->description('Only reports filed by this worker, from list-users.'),
            'result' => $schema->string()
                ->max(100)
                ->description('Exact 판단 name recorded on the report.'),
            'from' => $schema->string()
                ->format('date')
                ->description('Earliest activity date, YYYY-MM-DD.'),
            'to' => $schema->string()
                ->format('date')
                ->description('Latest activity date, YYYY-MM-DD.'),
            ...$this->pageSchema($schema),
        ];
    }
}
