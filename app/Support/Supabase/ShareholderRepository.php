<?php

namespace App\Support\Supabase;

/**
 * The 주주명부 — a project's shareholder roster, and by far the biggest table in
 * the database at ~1.45M rows.
 *
 * Nothing here may read a roster whole. Every query is bounded by a project and
 * a page; an unbounded select against this table would take the API down rather
 * than merely being slow.
 *
 * Note the shape: a Supabase `shareholder` row is one person *on one project*,
 * not a person. There is no global identity — the same human on two projects is
 * two rows — so the row id is what the API surfaces.
 */
class ShareholderRepository
{
    private const TABLE = 'shareholder';

    /**
     * Contact columns are deliberately absent: the API reports only whether a
     * contact exists (Pii::hasContact), never the number itself.
     */
    private const COLUMNS = 'id,project_id,name,registration,sex,person_type,date_of_birth,shares,shares_total,result,user,address,contact_info,contact_info_2,no';

    public function __construct(private readonly SupabaseClient $client) {}

    /**
     * @param  array<string, string|int>  $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function paginateForProject(int $projectId, array $filters, int $page, int $perPage): array
    {
        return $this->client->paginate(self::TABLE, [
            'select' => self::COLUMNS,
            'project_id' => 'eq.'.$projectId,
            'order' => 'no.asc,id.asc',
        ] + $filters, $page, $perPage);
    }
}
