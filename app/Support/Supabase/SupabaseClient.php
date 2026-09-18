<?php

namespace App\Support\Supabase;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The one way this app talks to Supabase: PostgREST over HTTP, GET only.
 *
 * Read-only is structural rather than a policy we remember to follow — there is
 * no method here that issues anything but a GET, so no amount of downstream code
 * can write to the client's production database. `SupabaseClientTest` pins that.
 *
 * Callers pass PostgREST query syntax directly (`project_id=eq.207`,
 * `order=no.asc`, `name=ilike.*김*`) because inventing a query builder over it
 * would hide the one thing worth seeing at the call site: exactly what we ask
 * the client's database for.
 */
class SupabaseClient
{
    public function __construct(
        private readonly string $url,
        private readonly string $key,
        private readonly int $timeout = 15,
    ) {}

    public static function fromConfig(): self
    {
        $url = (string) config('leenmore.supabase.url');
        $key = (string) config('leenmore.supabase.key');

        if ($url === '' || $key === '') {
            throw new RuntimeException(
                'Supabase is not configured: set SUPABASE_URL and SUPABASE_KEY in the environment.'
            );
        }

        return new self($url, $key, (int) config('leenmore.supabase.timeout', 15));
    }

    /**
     * Rows from one table.
     *
     * @param  array<string, string|int>  $query  PostgREST filters, verbatim
     * @return list<array<string, mixed>>
     */
    public function select(string $table, array $query = []): array
    {
        return $this->get($table, $query)->json() ?? [];
    }

    /**
     * The first row of a query, or null. Used for id lookups, where PostgREST
     * still answers with an array.
     *
     * @param  array<string, string|int>  $query
     * @return array<string, mixed>|null
     */
    public function first(string $table, array $query = []): ?array
    {
        return $this->select($table, ['limit' => 1] + $query)[0] ?? null;
    }

    /**
     * One page of rows plus the total the pager needs.
     *
     * `count=exact` is a real COUNT on the client's database; on the 1.4M-row
     * shareholder table it costs roughly twice the page itself, which is the
     * price of an honest total.
     *
     * @param  array<string, string|int>  $query
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function paginate(string $table, array $query, int $page, int $perPage): array
    {
        $from = ($page - 1) * $perPage;

        $response = $this->get($table, $query + [
            'limit' => $perPage,
            'offset' => $from,
        ], ['Prefer' => 'count=exact']);

        return [
            'rows' => $response->json() ?? [],
            'total' => $this->totalFromContentRange($response, count($response->json() ?? [])),
        ];
    }

    /**
     * Call a Postgres function. Read-only ones only — `project_shareholder_result_tally`
     * is the one we use, and it is a plain aggregate.
     *
     * @param  array<string, string|int>  $args
     * @return list<array<string, mixed>>
     */
    public function rpc(string $function, array $args = []): array
    {
        return $this->get('rpc/'.$function, $args)->json() ?? [];
    }

    /**
     * @param  array<string, string|int>  $query
     * @param  array<string, string>  $headers
     */
    private function get(string $path, array $query = [], array $headers = []): Response
    {
        $response = $this->request($headers)->get($this->endpoint($path), $query);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Supabase request to %s failed (%d): %s',
                $path,
                $response->status(),
                // PostgREST puts the reason in `message`; fall back to the body.
                $response->json('message') ?? $response->body(),
            ));
        }

        return $response;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function request(array $headers = []): PendingRequest
    {
        return Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => 'Bearer '.$this->key,
            'Accept' => 'application/json',
        ] + $headers)
            ->timeout($this->timeout)
            ->retry(2, 200, throw: false)
            ->acceptJson();
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->url, '/').'/rest/v1/'.ltrim($path, '/');
    }

    /**
     * PostgREST reports the total in `Content-Range: 0-49/1449015`. A `*` total
     * means it declined to count, in which case the page size is the best we know.
     */
    private function totalFromContentRange(Response $response, int $fallback): int
    {
        $range = $response->header('Content-Range');

        if ($range === '' || ! str_contains($range, '/')) {
            return $fallback;
        }

        $total = substr($range, strpos($range, '/') + 1);

        return is_numeric($total) ? (int) $total : $fallback;
    }
}
