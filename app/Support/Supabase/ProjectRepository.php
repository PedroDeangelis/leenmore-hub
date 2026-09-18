<?php

namespace App\Support\Supabase;

use Illuminate\Support\Facades\Cache;

/**
 * Projects (주주총회 campaigns) and their judgment tallies.
 *
 * Only ~123 rows, read on nearly every request, and changed rarely — so the list
 * and the tally are cached briefly rather than re-fetched per call.
 */
class ProjectRepository
{
    private const TABLE = 'project';

    /** Every column we ever surface; `results` is the text[] ResultSet decodes. */
    private const COLUMNS = 'id,title,status,results,shares_issued,shares_target,start_date,end_date,message,link_manage_id';

    public function __construct(private readonly SupabaseClient $client) {}

    /**
     * @param  array<string, string|int>  $filters  PostgREST filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->client->paginate(self::TABLE, [
            'select' => self::COLUMNS,
            'order' => 'start_date.desc,id.desc',
        ] + $filters, $page, $perPage);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->remember("project.{$id}", fn (): ?array => $this->client->first(self::TABLE, [
            'select' => self::COLUMNS,
            'id' => 'eq.'.$id,
        ]));
    }

    /**
     * How many shareholders a project has, and how many of them are judged.
     *
     * Both come from the tally RPC rather than two counting queries, because on
     * the 1.4M-row shareholder table a COUNT per project is the single most
     * expensive thing this API could do.
     *
     * @return array{total: int, judged: int}
     */
    public function shareholderCounts(int $projectId): array
    {
        $buckets = $this->tally($projectId);

        $total = array_sum(array_column($buckets, 'cnt'));
        $unjudged = 0;

        foreach ($buckets as $bucket) {
            if (($bucket['bucket'] ?? null) === '__null__') {
                $unjudged += (int) $bucket['cnt'];
            }
        }

        return ['total' => $total, 'judged' => $total - $unjudged];
    }

    /**
     * The 판단 결과 현황 tally: one row per judgment bucket with a count and the
     * shares behind it, summed inside Postgres.
     *
     * Buckets are result indices as strings, plus two specials: `__null__` for
     * unjudged rows and `__eproxy__` for electronic proxies (전자위임).
     *
     * @return list<array<string, mixed>>
     */
    public function tally(int $projectId): array
    {
        return $this->remember(
            "project.{$projectId}.tally",
            fn (): array => $this->client->rpc('project_shareholder_result_tally', ['p_project_id' => $projectId]),
        );
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function remember(string $key, callable $callback): mixed
    {
        $ttl = (int) config('leenmore.supabase.cache_ttl', 0);

        return $ttl > 0
            ? Cache::remember('supabase.'.$key, $ttl, $callback)
            : $callback();
    }
}
