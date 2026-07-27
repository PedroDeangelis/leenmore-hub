<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\Paginates;
use App\Models\ProjectShareholder;
use App\Support\Pii;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
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

#[Name('list-shareholders')]
#[Title('List a project roster')]
#[Description("A project's shareholder roster (주주명부) — who holds how many shares, their current 판단 and which workers are assigned. Answers \"where do things stand\". 실명번호 is masked, contact numbers are never returned.")]
#[IsReadOnly]
#[IsIdempotent]
class ListShareholdersTool extends Tool
{
    use Paginates;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'result' => ['sometimes', 'string', 'max:100'],
            'proxy_only' => ['sometimes', 'boolean'],
            'judged' => ['sometimes', 'boolean'],
            'worker_id' => ['sometimes', 'integer', 'exists:users,id'],
            'search' => ['sometimes', 'string', 'max:100'],
            ...$this->pageRules(),
        ]);

        $query = ProjectShareholder::query()
            ->where('project_id', $validated['project_id'])
            ->with(['shareholder', 'result', 'workers:id,name'])
            ->orderBy('no');

        if (filled($validated['result'] ?? null)) {
            $query->whereHas('result', fn (Builder $q) => $q->where('name', $validated['result']));
        }

        // 위임 is any green 판단, never a single label — same rule as ActivityData.
        if ($validated['proxy_only'] ?? false) {
            $query->whereHas('result', fn (Builder $q) => $q->where('color', 'green'));
        }

        if (array_key_exists('judged', $validated)) {
            $validated['judged']
                ? $query->whereNotNull('result_id')
                : $query->whereNull('result_id');
        }

        if (filled($validated['worker_id'] ?? null)) {
            $query->whereHas('workers', fn (Builder $q) => $q->where('users.id', $validated['worker_id']));
        }

        if (filled($validated['search'] ?? null)) {
            $query->whereHas('shareholder', fn (Builder $q) => $q->where('name', 'like', '%'.$validated['search'].'%'));
        }

        return Response::structured($this->paginated($query, $validated, $this->row(...)));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ProjectShareholder $assignment): array
    {
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
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('The project whose roster to list, from list-projects.'),
            'result' => $schema->string()
                ->max(100)
                ->description('Exact 판단 name to filter by, e.g. "유보". Use proxy_only for 위임 rather than naming one variant.'),
            'proxy_only' => $schema->boolean()
                ->description('Only shareholders whose current 판단 is a 위임 (any green judgment). This is the correct way to count proxies.'),
            'judged' => $schema->boolean()
                ->description('true = only shareholders with a 판단 recorded, false = only those still outstanding.'),
            'worker_id' => $schema->integer()
                ->description('Only shareholders assigned to this worker, from list-users.'),
            'search' => $schema->string()
                ->max(100)
                ->description("Match against the shareholder's name."),
            ...$this->pageSchema($schema),
        ];
    }
}
