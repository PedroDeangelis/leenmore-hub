<?php

namespace Tests\Feature\Api;

use App\Support\ApiData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesSupabase;
use Tests\TestCase;

/**
 * The endpoints, against recorded PostgREST payloads.
 *
 * These lean deliberately on the details the audit notes got wrong, because
 * those are what will break first if the upstream shape shifts or the decoding
 * is "simplified": comma-formatted numbers, judgments as indices into a text[]
 * of JSON strings, and workers identified two different ways.
 */
class ApiEndpointsTest extends TestCase
{
    use FakesSupabase;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeSupabase();
    }

    public function test_projects_report_comma_formatted_shares_as_whole_numbers(): void
    {
        $projects = ApiData::projects([]);

        $this->assertSame(1, $projects['total']);

        $project = $projects['data'][0];

        // "259,498,284" — a plain (int) cast would silently make this 259.
        $this->assertSame(259498284, $project['shares_issued']);
        $this->assertSame(14000000, $project['shares_target']);
        $this->assertSame('2026-09-11', $project['start_date']);
        $this->assertSame('2026-10-02', $project['meeting_date']);
    }

    public function test_a_project_decodes_its_judgment_options(): void
    {
        $project = ApiData::project(207);

        $this->assertCount(4, $project['results']);

        $first = $project['results'][0];
        $this->assertSame('위임(대면_서명)', $first['name']);
        $this->assertSame('green', $first['color']);
        $this->assertTrue($first['is_proxy'], 'a green 판단 is a 위임');

        // The last option carries no `order` key upstream; its array position is
        // still what a shareholder's `result` points at.
        $this->assertSame('단순부재', $project['results'][3]['name']);
        $this->assertSame(3, $project['results'][3]['id']);
        $this->assertFalse($project['results'][3]['is_proxy']);
    }

    public function test_the_judgment_tally_groups_by_colour_and_measures_against_target(): void
    {
        $tally = ApiData::project(207)['judgment_tally'];

        $this->assertTrue($tally['has_data']);

        // Buckets 0 (green) and 1 (lime) differ in colour, so they do not merge.
        $this->assertSame('green', $tally['groups'][0]['color']);
        $this->assertSame(3000, $tally['groups'][0]['total_shares']);
        $this->assertEqualsWithDelta(3000 / 14000000 * 100, $tally['groups'][0]['total_percent_of_target'], 0.0001);

        // Electronic proxies and unjudged rows are counted apart from the bands.
        $this->assertSame(1, $tally['electronic_proxy']['count']);
        $this->assertSame(6, $tally['unjudged']['count']);
    }

    public function test_the_roster_masks_personal_data(): void
    {
        $roster = ApiData::shareholders(207, []);
        $holder = $roster['data'][0];

        $this->assertSame('780913-2******', $holder['registration_masked']);
        $this->assertSame(1978, $holder['birth_year'], 'derived from the RRN, not the unreliable date column');
        $this->assertSame('서울특별시 송파구', $holder['region']);
        $this->assertTrue($holder['has_contact']);

        // The raw values must not appear anywhere in the payload.
        $json = json_encode($roster, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('780913-2345678', $json);
        $this->assertStringNotContainsString('010-0000-0000', $json);
        $this->assertStringNotContainsString('어딘가로', $json);
    }

    public function test_the_roster_resolves_a_judgment_index_to_its_name(): void
    {
        $roster = ApiData::shareholders(207, []);

        // result "0" is an index into the project's options, not a name.
        $this->assertSame('위임(대면_서명)', $roster['data'][0]['result']);
        $this->assertSame('green', $roster['data'][0]['result_color']);
        $this->assertTrue($roster['data'][0]['is_proxy']);
        $this->assertSame(2720000, $roster['data'][0]['shares']);
        $this->assertSame(['김활동'], $roster['data'][0]['workers']);

        // An unjudged row reports no judgment rather than guessing one.
        $this->assertNull($roster['data'][1]['result']);
        $this->assertFalse($roster['data'][1]['is_proxy']);
        $this->assertFalse($roster['data'][1]['has_contact']);
    }

    public function test_the_birth_year_handles_the_century_digit(): void
    {
        $roster = ApiData::shareholders(207, []);

        // 000312-3****** — century digit 3 means 2000, not 1900.
        $this->assertSame(2000, $roster['data'][1]['birth_year']);
    }

    public function test_submissions_redact_the_free_text_note(): void
    {
        $submissions = ApiData::submissions([]);
        $row = $submissions['data'][0];

        $this->assertSame('worker', $row['source']);
        $this->assertSame('위임(대면_서명)', $row['result']);
        $this->assertTrue($row['is_proxy']);
        $this->assertTrue($row['has_contact']);
        $this->assertSame(1, $row['file_count']);

        // Workers type phone numbers into the note: the memo comes through, the
        // contact details in it do not.
        $this->assertSame('본인 [전화번호] 통화, 2,720,000주 위임. 배우자 [실명번호] [이메일]', $row['note']);

        $this->assertSame('거부', $submissions['data'][1]['result']);
        $this->assertNull($submissions['data'][1]['note']);
        $this->assertTrue($submissions['data'][1]['privacy_consent']);
    }

    public function test_a_projects_submissions_include_its_esignon_proxies(): void
    {
        $this->fakeReportsNewestFirst();

        $submissions = ApiData::submissions(['project_id' => 207]);

        // The whitespace-only contact on 1004 is not a completed e-proxy.
        $this->assertSame(4, $submissions['total']);
        $this->assertSame(
            ['esignon:1003', 'worker:5002', 'worker:5001', 'esignon:1001'],
            $this->rowKeys($submissions['data']),
            'merged by day; on a shared day the worker report comes first',
        );

        $proxy = $submissions['data'][3];
        $this->assertNull($proxy['id'], 'an e-proxy is not a submission row');
        $this->assertSame('esignon', $proxy['source']);
        $this->assertSame('2026-09-15', $proxy['date']);
        $this->assertSame(207, $proxy['project_id']);
        $this->assertSame(1001, $proxy['shareholder_id']);
        $this->assertSame('위임(전자위임)', $proxy['result']);
        $this->assertSame('green', $proxy['result_color']);
        $this->assertTrue($proxy['is_proxy']);
        $this->assertTrue($proxy['has_contact']);
        $this->assertSame('김활동', $proxy['worker_name']);
        $this->assertNull($proxy['worker_id'], 'the roster names workers; it has no uuid to report');
        $this->assertArrayHasKey('note', $proxy);
        $this->assertNull($proxy['note'], 'an e-proxy has no worker memo');
        $this->assertNull($submissions['data'][0]['worker_name'], 'no worker assigned');

        // The signer's number is only ever reported as present.
        $json = json_encode($submissions, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('010-3333-4444', $json);
        $this->assertStringNotContainsString('010-5555-6666', $json);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/rest/v1/shareholder')
            && str_contains($request->url(), 'project_id=eq.207')
            && str_contains($request->url(), 'api_recipient_contact=neq.')
            && str_contains($request->url(), 'api_recipient_completion_date=neq.'));
    }

    public function test_esignon_proxies_join_only_when_scoped_to_a_project(): void
    {
        $submissions = ApiData::submissions([]);

        $this->assertSame(['worker'], array_values(array_unique(array_column($submissions['data'], 'source'))));

        // Unscoped, the lookup would be a full scan of the 1.45M-row roster.
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'api_recipient'));
    }

    public function test_merged_pages_neither_skip_nor_repeat(): void
    {
        $this->fakeReportsNewestFirst();

        $expected = ['esignon:1003', 'worker:5002', 'worker:5001', 'esignon:1001'];

        foreach ([1, 2, 3] as $perPage) {
            $seen = [];

            for ($page = 1; $page <= 5; $page++) {
                $result = ApiData::submissions(['project_id' => 207, 'page' => $page, 'per_page' => $perPage]);

                $this->assertSame(4, $result['total']);
                array_push($seen, ...$this->rowKeys($result['data']));
            }

            $this->assertSame($expected, $seen, "per_page={$perPage}");
        }
    }

    public function test_the_esignon_result_filter_returns_only_proxies(): void
    {
        $submissions = ApiData::submissions(['project_id' => 207, 'result' => '위임(전자위임)']);

        $this->assertSame(2, $submissions['total'], 'exact, since no report is fetched to be filtered away');
        $this->assertSame(['esignon:1003', 'esignon:1001'], $this->rowKeys($submissions['data']));

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/rest/v1/submission'));
    }

    public function test_another_result_filter_leaves_esignon_out(): void
    {
        $submissions = ApiData::submissions(['project_id' => 207, 'result' => '거부']);

        $this->assertSame(['worker:5002'], $this->rowKeys($submissions['data']));

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'api_recipient'));
    }

    public function test_esignon_filters_cover_the_whole_to_day_and_match_the_worker_by_name(): void
    {
        ApiData::submissions([
            'project_id' => 207,
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'worker_id' => '11111111-1111-1111-1111-111111111111',
        ]);

        // The completion time is text, so "up to the 30th" means "before the 1st".
        Http::assertSent(fn (Request $request): bool => str_contains(
            urldecode($request->url()),
            'and=(api_recipient_completion_date.gte.2026-09-01,api_recipient_completion_date.lt.2026-10-01)',
        ) && str_contains(urldecode($request->url()), 'user=cs.{김활동}'));
    }

    public function test_receipts_total_the_filtered_set_and_never_filter_on_status(): void
    {
        $receipts = ApiData::receipts([]);

        $this->assertSame(14000.0, $receipts['data'][0]['amount']);
        $this->assertSame('식비', $receipts['data'][0]['category']);

        // status=false rows are ordinary receipts upstream, so both are present
        // and the flag is passed through untouched.
        $this->assertCount(2, $receipts['data']);
        $this->assertFalse($receipts['data'][0]['status']);
        $this->assertTrue($receipts['data'][1]['status']);

        $this->assertSame(61000.0, $receipts['total_amount']);
        $this->assertSame('유류비', $receipts['by_category'][0]['category']);
    }

    public function test_activity_data_counts_each_workers_latest_judgment(): void
    {
        $activity = ApiData::activityData(207, []);

        $this->assertFalse($activity['is_empty']);
        $this->assertSame(['2026-09-15', '2026-09-16'], $activity['all_activity_days']);
        $this->assertCount(1, $activity['workers']);

        $worker = $activity['workers'][0];
        $this->assertSame('김활동', $worker['name']);
        $this->assertSame(2, $worker['total_reports']);
        $this->assertSame(2, $worker['shareholders_contacted']);
        $this->assertSame(1, $worker['proxy_shareholders'], 'one of the two latest judgments is a 위임');
        $this->assertSame(2, $worker['days_worked']);
    }

    /**
     * Serve reports newest first and honour offset/limit, as PostgREST does for
     * the endpoint's `date.desc` query — which the merged paging relies on.
     */
    private function fakeReportsNewestFirst(): void
    {
        $reports = array_reverse($this->fakeSubmissions());

        $this->refakeSupabase([
            'https://example.supabase.co/rest/v1/project*' => Http::response([$this->fakeProject()]),
            'https://example.supabase.co/rest/v1/shareholder*' => Http::response($this->fakeEproxies()),
            'https://example.supabase.co/rest/v1/submission*' => function (Request $request) use ($reports) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                return Http::response(
                    array_slice($reports, (int) $query['offset'], (int) $query['limit']),
                    200,
                    ['Content-Range' => '*/'.count($reports)],
                );
            },
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function rowKeys(array $rows): array
    {
        // An e-proxy has no id of its own; its roster row identifies it.
        return array_map(fn (array $row): string => $row['source'].':'.($row['id'] ?? $row['shareholder_id']), $rows);
    }
}
