<?php

namespace App\Mcp\Concerns;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;

/**
 * Shared paging for the MCP list tools. Every list is capped so a broad question
 * ("show me everyone") cannot pull the database into a model's context window, and
 * every response reports `total` so the assistant can tell it is looking at a slice.
 */
trait Paginates
{
    private const PER_PAGE_DEFAULT = 50;

    private const PER_PAGE_MAX = 200;

    /**
     * The `page` / `per_page` arguments, merged into a tool's own schema.
     *
     * @return array<string, Type>
     */
    protected function pageSchema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()
                ->min(1)
                ->default(1)
                ->description('Which page of results to return.'),
            'per_page' => $schema->integer()
                ->min(1)
                ->max(self::PER_PAGE_MAX)
                ->default(self::PER_PAGE_DEFAULT)
                ->description('Rows per page, up to '.self::PER_PAGE_MAX.'.'),
        ];
    }

    /**
     * The matching validation rules, merged into a tool's own rules.
     *
     * @return array<string, string>
     */
    protected function pageRules(): array
    {
        return [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:'.self::PER_PAGE_MAX,
        ];
    }

    /**
     * Run a query through the paginator and map each row for output.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $validated
     * @param  Closure(mixed): array<string, mixed>  $map
     * @return array<string, mixed>
     */
    protected function paginated(Builder $query, array $validated, Closure $map): array
    {
        /** @var LengthAwarePaginator<int, Model> $page */
        $page = $query->paginate(
            perPage: min((int) ($validated['per_page'] ?? self::PER_PAGE_DEFAULT), self::PER_PAGE_MAX),
            page: (int) ($validated['page'] ?? 1),
        );

        return [
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'last_page' => $page->lastPage(),
            'data' => array_map($map, $page->items()),
        ];
    }
}
