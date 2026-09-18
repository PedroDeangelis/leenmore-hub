<?php

namespace Tests\Support;

use App\Support\Supabase\ProfileRepository;
use App\Support\Supabase\ProjectRepository;
use App\Support\Supabase\ReceiptRepository;
use App\Support\Supabase\ShareholderRepository;
use App\Support\Supabase\SubmissionRepository;
use App\Support\Supabase\SupabaseClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/**
 * Canned PostgREST responses, shaped exactly like the live service — including
 * the habits that make it awkward: `results` as a text[] of JSON strings,
 * judgments referenced by index, numbers as comma-formatted text, and workers
 * named on roster rows but uuid'd on submissions.
 *
 * Recorded from the real database and then sanitised: the roster carries names,
 * 실명번호 and addresses, so the people here are invented and the 실명번호 are
 * structurally valid but not real.
 */
trait FakesSupabase
{
    /**
     * Replace the stubs entirely. Http::fake() appends, so an earlier catch-all
     * would otherwise keep answering; this starts from a clean factory.
     */
    protected function refakeSupabase(array $stubs): void
    {
        app()->forgetInstance(Factory::class);
        Http::swap(app()->make(Factory::class));
        Http::fake($stubs);

        foreach ([
            SupabaseClient::class,
            ProjectRepository::class,
            ShareholderRepository::class,
            SubmissionRepository::class,
            ReceiptRepository::class,
            ProfileRepository::class,
        ] as $singleton) {
            app()->forgetInstance($singleton);
        }
    }

    protected function fakeSupabase(array $overrides = []): void
    {
        config([
            'leenmore.supabase.url' => 'https://example.supabase.co',
            'leenmore.supabase.key' => 'test-key',
            'leenmore.supabase.cache_ttl' => 0,
        ]);

        Http::fake($overrides + [
            'https://example.supabase.co/rest/v1/rpc/project_shareholder_result_tally*' => Http::response([
                ['bucket' => '0', 'cnt' => 2, 'total_shares' => 3000],
                ['bucket' => '1', 'cnt' => 1, 'total_shares' => 1000],
                ['bucket' => '3', 'cnt' => 1, 'total_shares' => 500],
                ['bucket' => '__null__', 'cnt' => 6, 'total_shares' => 9000],
                ['bucket' => '__eproxy__', 'cnt' => 1, 'total_shares' => 250],
            ]),

            'https://example.supabase.co/rest/v1/project*' => Http::response([$this->fakeProject()], 200, [
                'Content-Range' => '0-0/1',
            ]),

            'https://example.supabase.co/rest/v1/shareholder*' => Http::response($this->fakeRoster(), 200, [
                'Content-Range' => '0-1/2',
            ]),

            'https://example.supabase.co/rest/v1/submission*' => Http::response($this->fakeSubmissions(), 200, [
                'Content-Range' => '0-1/2',
            ]),

            'https://example.supabase.co/rest/v1/receipt*' => Http::response($this->fakeReceipts(), 200, [
                'Content-Range' => '0-1/2',
            ]),

            'https://example.supabase.co/rest/v1/profiles*' => Http::response([
                ['id' => '11111111-1111-1111-1111-111111111111', 'first_name' => '김활동', 'last_name' => null],
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fakeProject(): array
    {
        return [
            'id' => 207,
            'title' => '테스트_2026년 임총',
            'status' => 'publish',
            // text[] of JSON strings, and note the last entry carries no `order`
            // key — which is true of most rows upstream.
            'results' => [
                '{"name":"위임(대면_서명)","color":"green","contactRequired":true,"attachmentRequired":true,"order":0}',
                '{"name":"위임대기","color":"lime","contactRequired":false,"attachmentRequired":false,"order":1}',
                '{"name":"거부","color":"red","contactRequired":false,"attachmentRequired":false,"order":2}',
                '{"name":"단순부재","color":"yellow","contactRequired":false,"attachmentRequired":false}',
            ],
            'shares_issued' => '259,498,284',
            'shares_target' => '14,000,000',
            'start_date' => '2026-09-11T00:00:00+00:00',
            'end_date' => '2026-10-02T00:00:00+00:00',
            'message' => '테스트 메시지',
            'link_manage_id' => '95',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fakeRoster(): array
    {
        return [
            [
                'id' => 1001, 'project_id' => 207, 'name' => '홍길동',
                'registration' => '780913-2345678', 'sex' => 'F', 'person_type' => null,
                'date_of_birth' => '470847600000', 'shares' => '2,720,000', 'shares_total' => '2,720,000',
                'result' => '0', 'user' => ['김활동'], 'address' => '서울특별시 송파구 어딘가로 1',
                'contact_info' => '010-0000-0000', 'contact_info_2' => null, 'no' => 3,
            ],
            [
                'id' => 1002, 'project_id' => 207, 'name' => '김철수',
                'registration' => '000312-3456789', 'sex' => 'M', 'person_type' => null,
                'date_of_birth' => '12056', 'shares' => '1,331', 'shares_total' => '1,331',
                'result' => null, 'user' => [], 'address' => null,
                'contact_info' => null, 'contact_info_2' => null, 'no' => 4,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fakeSubmissions(): array
    {
        return [
            [
                'id' => 5001, 'project_id' => 207, 'shareholder_id' => 1001,
                'user_id' => '11111111-1111-1111-1111-111111111111', 'user_name' => '김활동',
                'date' => '2026-09-15T00:00:00+00:00', 'result' => '0', 'contact_worker' => '010-1111-2222',
                'files' => ['a.jpg'], 'privacy_consent_file' => [], 'created_at' => '2026-09-15T01:00:00+00:00',
            ],
            [
                'id' => 5002, 'project_id' => 207, 'shareholder_id' => 1002,
                'user_id' => '11111111-1111-1111-1111-111111111111', 'user_name' => '김활동',
                'date' => '2026-09-16T00:00:00+00:00', 'result' => '2', 'contact_worker' => null,
                'files' => [], 'privacy_consent_file' => ['c.pdf'], 'created_at' => '2026-09-16T01:00:00+00:00',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fakeReceipts(): array
    {
        return [
            [
                'id' => 9001, 'date' => '2026-03-07T00:00:00+00:00', 'usage_history' => '식비',
                'where_used' => '삼거리순대국', 'amount' => '14,000',
                'user_id' => '11111111-1111-1111-1111-111111111111', 'user_name' => '김활동',
                'attachments' => ['r.jpg'], 'status' => false, 'created_at' => '2026-03-07T01:00:00+00:00',
            ],
            [
                'id' => 9002, 'date' => '2026-03-08T00:00:00+00:00', 'usage_history' => '유류비',
                'where_used' => '단봉주유소', 'amount' => '47,000',
                'user_id' => '11111111-1111-1111-1111-111111111111', 'user_name' => '김활동',
                'attachments' => [], 'status' => true, 'created_at' => '2026-03-08T01:00:00+00:00',
            ],
        ];
    }
}
