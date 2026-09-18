<?php

namespace Tests\Feature\Api;

use App\Support\ApiData;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_submissions_withhold_the_free_text_note(): void
    {
        $submissions = ApiData::submissions([]);
        $row = $submissions['data'][0];

        $this->assertSame('위임(대면_서명)', $row['result']);
        $this->assertTrue($row['is_proxy']);
        $this->assertTrue($row['has_contact']);
        $this->assertSame(1, $row['file_count']);
        $this->assertArrayNotHasKey('note', $row, 'workers type phone numbers into the note');

        $this->assertSame('거부', $submissions['data'][1]['result']);
        $this->assertTrue($submissions['data'][1]['privacy_consent']);
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
}
