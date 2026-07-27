<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\Paginates;
use App\Models\User;
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

#[Name('list-users')]
#[Title('List staff and workers')]
#[Description('Hub accounts: field workers (활동가) and back-office staff, with their role and whether the account is active. Use it to resolve a worker name to the worker_id the other tools take.')]
#[IsReadOnly]
#[IsIdempotent]
class ListUsersTool extends Tool
{
    use Paginates;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'role' => ['sometimes', 'string', 'in:admin,office,worker'],
            'active' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:100'],
            ...$this->pageRules(),
        ]);

        $query = User::query()->orderBy('name');

        if (filled($validated['role'] ?? null)) {
            $query->where('role', $validated['role']);
        }

        if (array_key_exists('active', $validated)) {
            $validated['active']
                ? $query->whereNull('deactivated_at')
                : $query->whereNotNull('deactivated_at');
        }

        if (filled($validated['search'] ?? null)) {
            $query->where('name', 'like', '%'.$validated['search'].'%');
        }

        return Response::structured($this->paginated($query, $validated, $this->row(...)));
    }

    /**
     * Staff phone numbers are personal contact details like any other, so only
     * their presence is reported.
     *
     * @return array<string, mixed>
     */
    private function row(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role->value,
            'is_active' => $user->deactivated_at === null,
            'deactivated_at' => $user->deactivated_at?->toDateString(),
            'has_phone' => filled($user->phone),
            'locale' => $user->locale,
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'role' => $schema->string()
                ->enum(['admin', 'office', 'worker'])
                ->description('worker = field worker (활동가); admin and office are back-office staff.'),
            'active' => $schema->boolean()
                ->description('true = active accounts only, false = deactivated only. Omit for both.'),
            'search' => $schema->string()
                ->max(100)
                ->description("Match against the account's name."),
            ...$this->pageSchema($schema),
        ];
    }
}
