<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\Paginates;
use App\Models\Receipt;
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

#[Name('list-receipts')]
#[Title('List expense receipts')]
#[Description('Expense receipts (영수증) submitted by field workers, with the summed total and a per-category breakdown for the filtered set. Amounts are Korean won.')]
#[IsReadOnly]
#[IsIdempotent]
class ListReceiptsTool extends Tool
{
    use Paginates;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'worker_id' => ['sometimes', 'integer', 'exists:users,id'],
            'category' => ['sometimes', 'string', 'max:100'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            ...$this->pageRules(),
        ]);

        $filtered = fn (): Builder => Receipt::query()
            ->when(filled($validated['worker_id'] ?? null), fn (Builder $q) => $q->where('user_id', $validated['worker_id']))
            ->when(filled($validated['category'] ?? null), fn (Builder $q) => $q->where('category_name', $validated['category']))
            ->when(filled($validated['from'] ?? null), fn (Builder $q) => $q->whereDate('date', '>=', $validated['from']))
            ->when(filled($validated['to'] ?? null), fn (Builder $q) => $q->whereDate('date', '<=', $validated['to']));

        // Totals cover the whole filtered set, not just the page, so "how much did we
        // spend on X" is answerable without paging through every row.
        $page = $this->paginated(
            $filtered()->orderByDesc('date')->orderByDesc('id'),
            $validated,
            $this->row(...),
        );

        return Response::structured([
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
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Receipt $receipt): array
    {
        return [
            'id' => $receipt->id,
            'date' => $receipt->date?->toDateString(),
            'worker_id' => $receipt->user_id,
            'worker_name' => $receipt->user_name,
            'category' => $receipt->category_name,
            'vendor' => $receipt->vendor,
            'amount' => $receipt->amount,
            'has_attachment' => filled($receipt->attachment),
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'worker_id' => $schema->integer()
                ->description('Only receipts submitted by this worker, from list-users.'),
            'category' => $schema->string()
                ->max(100)
                ->description('Exact usage category (사용 내역) name.'),
            'from' => $schema->string()
                ->format('date')
                ->description('Earliest receipt date, YYYY-MM-DD.'),
            'to' => $schema->string()
                ->format('date')
                ->description('Latest receipt date, YYYY-MM-DD.'),
            ...$this->pageSchema($schema),
        ];
    }
}
