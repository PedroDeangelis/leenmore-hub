<?php

namespace App\Support\Supabase;

/**
 * Worker expense receipts.
 *
 * Note what is *not* here: a filter on `status`. The audit notes called it a
 * delete flag (true = active), but in the live data only 771 of 11,083 rows are
 * true and the false ones are ordinary receipts — 식비, 유류비, 주차비 against
 * real vendors. Filtering on it would hide 93% of the data. It is surfaced as a
 * plain field until the client tells us what it means.
 *
 * There is also no categories table; the client's UI uses the free-text
 * `usage_history` as the category, so that is what the `category` filter matches.
 */
class ReceiptRepository
{
    private const TABLE = 'receipt';

    private const COLUMNS = 'id,date,usage_history,where_used,amount,user_id,user_name,attachments,status,created_at';

    public function __construct(private readonly SupabaseClient $client) {}

    /**
     * @param  array<string, string|int>  $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->client->paginate(self::TABLE, [
            'select' => self::COLUMNS,
            'order' => 'date.desc,id.desc',
        ] + $filters, $page, $perPage);
    }

    /**
     * Rows matching the same filters but unpaginated, for the totals the
     * receipts endpoint reports alongside the page.
     *
     * @param  array<string, string|int>  $filters
     * @return list<array<string, mixed>>
     */
    public function allMatching(array $filters, int $limit = 5000): array
    {
        return $this->client->select(self::TABLE, [
            'select' => 'amount,usage_history',
            'limit' => $limit,
        ] + $filters);
    }
}
