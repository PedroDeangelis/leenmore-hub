<?php

namespace Tests\Feature\Esignon;

use App\Services\Esignon\EsignonClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EsignonClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.esignon', [
            'base_url' => 'https://docs.esignon.net/api',
            'company_id' => 'testco',
            'email' => 'test@example.com',
            'password' => 'secret',
        ]);
    }

    public function test_it_logs_in_then_downloads_and_parses_records(): void
    {
        // The download body is a delimited spreadsheet: known columns plus free
        // `Text_*` columns the parser must resolve into name / DOB / contact.
        $body = implode("\n", [
            '완료된 링크 문서 ID,완료일,요청자 이메일,Text_0,Text_1,Text_2',
            'DOC123,2026-02-06 12:11:56 UTC(+09:00),signer@example.com,홍길동,900101,010-1234-5678',
        ]);

        Http::fake([
            '*login*' => Http::response(['body' => ['access_token' => 'tok-123', 'expire_date' => date('Y-m-d H:i:s', time() + 3600)]]),
            '*excel*' => Http::response($body),
        ]);

        $records = (new EsignonClient)->download('5001');

        $this->assertCount(1, $records);
        $this->assertSame('DOC123', $records[0]['link_id']);
        $this->assertSame('2026-02-06 12:11:56 UTC(+09:00)', $records[0]['completed_date']);
        $this->assertSame('홍길동', $records[0]['name']);     // inferred from Text_0
        $this->assertSame('900101', $records[0]['dob']);       // inferred from Text_1
        $this->assertSame('010-1234-5678', $records[0]['contact']); // inferred from Text_2

        // Logged in against the company endpoint, then called the link excel
        // endpoint with the bearer token.
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/testco/login'));
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/v3/link/5001/excel')
            && $r->hasHeader('Authorization', 'esignon tok-123'));
    }

    public function test_a_404_means_no_completed_documents_yet(): void
    {
        Http::fake([
            '*login*' => Http::response(['body' => ['access_token' => 't', 'expire_date' => date('Y-m-d H:i:s', time() + 3600)]]),
            '*excel*' => Http::response(['message' => '완료된 링크문서가 없어 다운로드 할 수 없습니다.'], 404),
        ]);

        $this->assertSame([], (new EsignonClient)->download('5001'));
    }

    public function test_the_token_is_cached_across_downloads(): void
    {
        Http::fake([
            '*login*' => Http::response(['body' => ['access_token' => 'tok-xyz', 'expire_date' => date('Y-m-d H:i:s', time() + 3600)]]),
            '*excel*' => Http::response(''),
        ]);

        $client = new EsignonClient;
        $client->download('1');
        $client->download('2');

        // Two downloads, but only one login thanks to the cached token.
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/testco/login'));
    }
}
