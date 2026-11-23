<?php

namespace Tests\Feature\Api;

use App\Support\ApiData;
use App\Support\Supabase\Num;
use App\Support\Supabase\ResultSet;
use App\Support\Supabase\SupabaseClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\FakesSupabase;
use Tests\TestCase;

/**
 * The client itself, and the two small classes that absorb Supabase's storage
 * habits. The GET-only assertion is the important one: it is what makes
 * "read-only" a property of the code rather than a promise.
 */
class SupabaseClientTest extends TestCase
{
    use FakesSupabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeSupabase();
    }

    public function test_every_request_is_a_get(): void
    {
        ApiData::projects([]);
        ApiData::project(207);
        ApiData::shareholders(207, []);
        ApiData::submissions([]);
        ApiData::submissions(['project_id' => 207]);
        ApiData::receipts([]);
        ApiData::activityData(207, []);

        Http::assertSentCount(Http::recorded()->count());

        Http::recorded()->each(function (array $pair): void {
            /** @var Request $request */
            $request = $pair[0];

            $this->assertSame('GET', $request->method(), 'the client must never write to the client\'s database');
        });

        $this->assertGreaterThan(0, Http::recorded()->count(), 'the requests really were made');
    }

    public function test_it_sends_the_api_key_both_ways_postgrest_expects(): void
    {
        ApiData::projects([]);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('apikey', 'test-key')
            && $request->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_a_failed_response_raises_rather_than_returning_nothing(): void
    {
        $this->refakeSupabase(['*' => Http::response(['message' => 'permission denied for table project'], 403)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('permission denied for table project');

        app(SupabaseClient::class)->select('project');
    }

    public function test_it_reads_the_total_from_the_content_range_header(): void
    {
        $this->refakeSupabase(['*' => Http::response([['id' => 1]], 200, ['Content-Range' => '0-0/1449015'])]);

        $page = app(SupabaseClient::class)->paginate('shareholder', [], 1, 1);

        $this->assertSame(1449015, $page['total']);
    }

    public function test_it_falls_back_when_postgrest_declines_to_count(): void
    {
        $this->refakeSupabase(['*' => Http::response([['id' => 1], ['id' => 2]], 200, ['Content-Range' => '0-1/*'])]);

        $page = app(SupabaseClient::class)->paginate('shareholder', [], 1, 50);

        $this->assertSame(2, $page['total']);
    }

    /**
     * @return list<array{string|null, int|null}>
     */
    public static function numberProvider(): array
    {
        return [
            ['259,498,284', 259498284],
            ['14,000', 14000],
            ['1', 1],
            ['0', 0],
            ['', null],
            ['N/A', null],
            ['-', null],
            [null, null],
        ];
    }

    #[DataProvider('numberProvider')]
    public function test_comma_formatted_text_becomes_a_number(?string $raw, ?int $expected): void
    {
        $this->assertSame($expected, Num::int($raw));
    }

    public function test_an_out_of_range_judgment_index_resolves_to_nothing(): void
    {
        $results = ResultSet::fromColumn($this->fakeProject()['results']);

        $this->assertSame('거부', $results->resolve('2')?->name);

        // Happens when the client shortens a project's option list after rows
        // already referenced the removed entries.
        $this->assertNull($results->resolve('99'));
        $this->assertNull($results->resolve(null));
        $this->assertNull($results->resolve('not-a-number'));
    }

    public function test_malformed_result_entries_are_skipped_not_fatal(): void
    {
        $results = ResultSet::fromColumn([
            '{"name":"위임","color":"green"}',
            'not json at all',
            '{"color":"red"}', // no name
            '{"name":"거부","color":"red"}',
        ]);

        $this->assertCount(2, $results->judgments);
        $this->assertSame(['위임', '거부'], array_map(fn ($j): string => $j->name, $results->judgments));
    }

    public function test_proxy_indices_are_the_green_ones(): void
    {
        $results = ResultSet::fromColumn($this->fakeProject()['results']);

        $this->assertSame(['0'], $results->proxyIndices());
    }
}
