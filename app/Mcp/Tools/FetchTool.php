<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use App\Models\Shareholder;
use App\Models\Submission;
use App\Models\User;
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

/**
 * The `fetch` half of the search/fetch pair, and the only place a free-text note
 * is returned — one record per call. Everything else stays redacted exactly as it
 * is in the list tools.
 *
 * @see SearchTool for the other half.
 */
#[Name('fetch')]
#[Title('Fetch one record')]
#[Description('Fetch a single record in full by its prefixed id: "project:12", "shareholder:840", "user:7" or "submission:1503". This is the only tool that returns an activity report\'s free-text note. 실명번호 stays masked and contact numbers are still never returned.')]
#[IsReadOnly]
#[IsIdempotent]
class FetchTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'regex:/^(project|shareholder|user|submission):\d+$/'],
        ]);

        [$type, $id] = explode(':', $validated['id'], 2);

        $record = match ($type) {
            'project' => $this->project((int) $id),
            'shareholder' => $this->shareholder((int) $id),
            'user' => $this->user((int) $id),
            'submission' => $this->submission((int) $id),
            default => null,
        };

        return $record === null
            ? Response::error('No '.$type.' with id '.$id.'.')
            : Response::structured($record);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function project(int $id): ?array
    {
        $project = Project::query()->withCount(['shareholders', 'submissions'])->find($id);

        return $project === null ? null : [
            'id' => 'project:'.$project->id,
            'type' => 'project',
            'title' => $project->title,
            'url' => route('projects.show', $project),
            'status' => $project->status->value,
            'message' => $project->message,
            'start_date' => $project->start_date?->toDateString(),
            'meeting_date' => $project->end_date?->toDateString(),
            'shares_issued' => $project->shares_issued,
            'shares_target' => $project->shares_target,
            'shareholder_count' => $project->shareholders_count,
            'submission_count' => $project->submissions_count,
            'hint' => 'Call get-project with project_id '.$project->id.' for the 판단 tally.',
        ];
    }

    /**
     * The person plus where they stand on every project they are on. Their base
     * contact and full address are not part of the response.
     *
     * @return array<string, mixed>|null
     */
    private function shareholder(int $id): ?array
    {
        $shareholder = Shareholder::query()
            ->with(['assignments.project:id,title', 'assignments.result'])
            ->find($id);

        return $shareholder === null ? null : [
            'id' => 'shareholder:'.$shareholder->id,
            'type' => 'shareholder',
            'title' => $shareholder->name,
            'person_type' => $shareholder->person_type?->value,
            'registration_masked' => Pii::registration($shareholder->registration),
            'birth_year' => Pii::birthYear($shareholder->date_of_birth),
            'region' => Pii::address($shareholder->address),
            'has_contact' => Pii::hasContact($shareholder->contact_info),
            'assignments' => $shareholder->assignments
                ->filter(fn ($assignment): bool => $assignment->project !== null)
                ->map(fn ($assignment): array => [
                    'project_id' => $assignment->project_id,
                    'project_title' => $assignment->project?->title,
                    'project_shareholder_id' => $assignment->id,
                    'shares' => $assignment->shares,
                    'result' => $assignment->result?->name,
                    'result_color' => $assignment->result?->color->value,
                    'is_proxy' => $assignment->result?->color->value === 'green',
                    'region' => Pii::address($assignment->effective_address),
                    'has_contact' => Pii::hasContact($assignment->effective_contact, $assignment->contact_info_2),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function user(int $id): ?array
    {
        $user = User::query()->withCount(['assignedShareholders', 'receipts'])->find($id);

        return $user === null ? null : [
            'id' => 'user:'.$user->id,
            'type' => 'user',
            'title' => $user->name,
            'url' => route('users.show', $user),
            'role' => $user->role->value,
            'is_active' => $user->deactivated_at === null,
            'deactivated_at' => $user->deactivated_at?->toDateString(),
            'has_phone' => filled($user->phone),
            'locale' => $user->locale,
            'assigned_shareholder_count' => $user->assigned_shareholders_count,
            'receipt_count' => $user->receipts_count,
        ];
    }

    /**
     * One activity report, including the worker's free-text note. The note is the
     * reason this tool exists rather than widening the list tools: it can contain
     * anything the worker typed, so it is disclosed one record at a time.
     *
     * @return array<string, mixed>|null
     */
    private function submission(int $id): ?array
    {
        $submission = Submission::query()
            ->with(['project:id,title', 'projectShareholder.shareholder:id,name'])
            ->find($id);

        return $submission === null ? null : [
            'id' => 'submission:'.$submission->id,
            'type' => 'submission',
            'title' => ($submission->projectShareholder?->shareholder?->name ?? 'Unknown').' — '.($submission->date?->toDateString() ?? 'undated'),
            'date' => $submission->date?->toDateString(),
            'project_id' => $submission->project_id,
            'project_title' => $submission->project?->title,
            'project_shareholder_id' => $submission->project_shareholder_id,
            'worker_id' => $submission->user_id,
            'worker_name' => $submission->user_name,
            'result' => $submission->result,
            'note' => $submission->note,
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
            'id' => $schema->string()
                ->required()
                ->description('A prefixed record id — "project:12", "shareholder:840", "user:7" or "submission:1503". Search returns these; list-submissions returns the numeric id to prefix with "submission:".'),
        ];
    }
}
