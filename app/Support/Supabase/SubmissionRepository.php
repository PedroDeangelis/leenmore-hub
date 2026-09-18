<?php

namespace App\Support\Supabase;

/**
 * 활동 보고 — the per-visit reports a worker files against a shareholder.
 *
 * `is_deleted` is a genuine soft-delete flag and is always filtered out.
 * `note` is never selected: workers type phone numbers into it, so it is
 * personal data the API does not carry.
 */
class SubmissionRepository
{
    private const TABLE = 'submission';

    private const COLUMNS = 'id,project_id,shareholder_id,user_id,user_name,date,result,contact_worker,files,privacy_consent_file,created_at';

    public function __construct(private readonly SupabaseClient $client) {}

    /**
     * @param  array<string, string|int>  $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->client->paginate(self::TABLE, [
            'select' => self::COLUMNS,
            'is_deleted' => 'is.false',
            'order' => 'date.desc,id.desc',
        ] + $filters, $page, $perPage);
    }

    /**
     * Every live submission on a project, oldest first — the order
     * ActivityData depends on to let later reports overwrite earlier ones.
     *
     * Bounded by project, and by the day filter when one is given.
     *
     * @param  array<string, string|int>  $filters
     * @return list<array<string, mixed>>
     */
    public function forProject(int $projectId, array $filters = [], int $limit = 5000): array
    {
        return $this->client->select(self::TABLE, [
            'select' => self::COLUMNS,
            'project_id' => 'eq.'.$projectId,
            'is_deleted' => 'is.false',
            'order' => 'date.asc,created_at.asc,id.asc',
            'limit' => $limit,
        ] + $filters);
    }
}
