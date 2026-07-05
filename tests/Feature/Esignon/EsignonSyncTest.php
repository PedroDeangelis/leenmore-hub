<?php

namespace Tests\Feature\Esignon;

use App\Models\EsignonShareholder;
use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use App\Services\Esignon\EsignonSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EsignonSyncTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_match_writes_api_recipient_fields_and_leaves_unmatched_signers_flagged(): void
    {
        $project = Project::factory()->create(['link_manage_id' => '5001']);

        // A roster assignment whose person matches a signer by name + DOB code.
        $person = Shareholder::factory()->create(['name' => '홍길동', 'date_of_birth_code' => '900101']);
        $assignment = ProjectShareholder::factory()->for($project)->for($person)->create();

        $matchedSigner = EsignonShareholder::factory()->for($project)->create([
            'name' => '홍길동', 'identifier' => '900101',
            'contact' => '010-1234-5678', 'completed_date' => '2026-02-06 12:11:56',
            'needs_sync' => true,
        ]);
        $missingSigner = EsignonShareholder::factory()->for($project)->create([
            'name' => '없는사람', 'identifier' => '000000', 'needs_sync' => true,
        ]);

        $result = app(EsignonSync::class)->match($project);

        $this->assertSame(['matched' => 1, 'missing' => 1], $result);

        // The matched assignment received the two ESIGNON fields.
        $assignment->refresh();
        $this->assertSame('010-1234-5678', $assignment->api_recipient_contact);
        $this->assertSame('2026-02-06', $assignment->api_recipient_completion_date->toDateString());

        // Matched signer cleared; unmatched signer stays flagged (it's a 누락).
        $this->assertFalse($matchedSigner->refresh()->needs_sync);
        $this->assertNotNull($matchedSigner->synced_at);
        $this->assertTrue($missingSigner->refresh()->needs_sync);
    }

    public function test_the_command_pulls_from_esignon_then_matches_and_is_idempotent(): void
    {
        $project = Project::factory()->create(['link_manage_id' => '5001']);
        $person = Shareholder::factory()->create(['name' => '홍길동', 'date_of_birth_code' => '900101']);
        $assignment = ProjectShareholder::factory()->for($project)->for($person)->create();

        $body = implode("\n", [
            '완료된 링크 문서 ID,완료일,요청자 이메일,Text_0,Text_1,Text_2',
            'DOC123,2026-02-06 12:11:56 UTC(+09:00),signer@example.com,홍길동,900101,01012345678',
        ]);

        Http::fake([
            '*login*' => Http::response(['body' => ['access_token' => 'tok-123', 'expire_date' => date('Y-m-d H:i:s', time() + 3600)]]),
            '*excel*' => Http::response($body),
        ]);

        $this->artisan('esignon:sync', ['--project' => $project->id])->assertSuccessful();

        // One document stored, matched onto the assignment (phone re-formatted).
        $this->assertDatabaseCount('esignon_shareholders', 1);
        $assignment->refresh();
        $this->assertSame('010-1234-5678', $assignment->api_recipient_contact);
        $this->assertSame('2026-02-06', $assignment->api_recipient_completion_date->toDateString());

        $signer = EsignonShareholder::firstWhere('link_id', 'DOC123');
        $this->assertFalse($signer->needs_sync);

        // Re-running pulls the same (unchanged) document — no new rows, no churn.
        $this->artisan('esignon:sync', ['--project' => $project->id])->assertSuccessful();
        $this->assertDatabaseCount('esignon_shareholders', 1);
        $this->assertFalse($signer->refresh()->needs_sync);
    }

    public function test_it_only_syncs_active_projects_with_a_link_when_unfiltered(): void
    {
        Project::factory()->create(['link_manage_id' => null]);          // no link
        $withLink = Project::factory()->create(['link_manage_id' => '7']);

        Http::fake([
            '*login*' => Http::response(['body' => ['access_token' => 't', 'expire_date' => date('Y-m-d H:i:s', time() + 3600)]]),
            '*excel*' => Http::response(''),
        ]);

        $this->artisan('esignon:sync')->assertSuccessful();

        // Only the project that has an ESIGNON link was downloaded.
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/v3/link/7/excel'));
        Http::assertSentCount(2); // 1 login + 1 download
    }
}
