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

    /**
     * The contact is selected only to confirm one exists; it never leaves the
     * API (Pii::hasContact).
     */
    private const EPROXY_COLUMNS = 'id,project_id,user,api_recipient_contact,api_recipient_completion_date';

    /** Supabase silently caps a response at 1000 rows. */
    private const EPROXY_PAGE = 1000;

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

    /**
     * A project's completed electronic proxies (전자위임), newest first.
     *
     * eSignon writes the signer's contact and completion time back onto the roster
     * row; a proxy counts as completed only when both are filled. That is the one
     * definition of an e-proxy here — the same one the client's own admin uses.
     *
     * Bounded by project: across all projects the same filter is a full scan of
     * the roster, which takes seconds and times out once ordered.
     *
     * @param  array<string, string|int>  $filters
     * @return list<array<string, mixed>>
     */
    public function eproxiesForProject(int $projectId, array $filters = []): array
    {
        $rows = [];

        for ($offset = 0; ; $offset += self::EPROXY_PAGE) {
            $page = $this->client->select(self::TABLE, [
                'select' => self::EPROXY_COLUMNS,
                'project_id' => 'eq.'.$projectId,
                // `<> ''` is also false for NULL, so this excludes both.
                'api_recipient_contact' => 'neq.',
                'api_recipient_completion_date' => 'neq.',
                'order' => 'api_recipient_completion_date.desc,id.desc',
                'limit' => self::EPROXY_PAGE,
                'offset' => $offset,
            ] + $filters);

            array_push($rows, ...$page);

            if (count($page) < self::EPROXY_PAGE) {
                break;
            }
        }

        // Whitespace-only values pass the filter above but are not a completion.
        return array_values(array_filter($rows, static fn (array $row): bool => trim((string) $row['api_recipient_contact']) !== ''
            && trim((string) $row['api_recipient_completion_date']) !== ''));
    }
}
