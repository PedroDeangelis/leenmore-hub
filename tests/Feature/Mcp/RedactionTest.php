<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\LeenmoreServer;
use App\Mcp\Tools\FetchTool;
use App\Mcp\Tools\ListShareholdersTool;
use App\Mcp\Tools\ListSubmissionsTool;
use App\Mcp\Tools\ListUsersTool;
use App\Mcp\Tools\SearchTool;
use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The privacy boundary. Everything these tools return is transmitted to OpenAI /
 * Anthropic / Google and may be retained there, so each of these is a
 * characterisation of what must never appear in a response — not a style check.
 */
class RedactionTest extends McpTestCase
{
    use RefreshDatabase;

    private const RRN = '901231-1234567';

    private const PHONE = '010-9876-5432';

    private const ADDRESS = '서울특별시 강남구 테헤란로 152 강남파이낸스센터 20층';

    private function shareholderOnAProject(): ProjectShareholder
    {
        $person = Shareholder::factory()->create([
            'name' => '박하늘',
            'registration' => self::RRN,
            'contact_info' => self::PHONE,
            'address' => self::ADDRESS,
            'date_of_birth' => '1990-12-31',
        ]);

        return ProjectShareholder::factory()
            ->for(Project::factory()->create())
            ->create(['shareholder_id' => $person->id]);
    }

    public function test_a_roster_masks_the_resident_number_and_omits_contact_and_full_address(): void
    {
        $assignment = $this->shareholderOnAProject();

        $payload = LeenmoreServer::tool(ListShareholdersTool::class, [
            'project_id' => $assignment->project_id,
        ])->assertOk()->payload();

        $row = $payload['data'][0];

        $this->assertSame('901231-1******', $row['registration_masked']);
        $this->assertSame('서울특별시 강남구', $row['region']);
        $this->assertSame(1990, $row['birth_year']);
        $this->assertTrue($row['has_contact']);

        $body = $this->flatten($payload);
        $this->assertStringNotContainsString(self::RRN, $body);
        $this->assertStringNotContainsString('1234567', $body);
        $this->assertStringNotContainsString(self::PHONE, $body);
        $this->assertStringNotContainsString('테헤란로', $body);
        $this->assertArrayNotHasKey('contact_info', $row);
        $this->assertArrayNotHasKey('address', $row);
    }

    public function test_fetching_one_shareholder_is_redacted_exactly_like_the_list(): void
    {
        $assignment = $this->shareholderOnAProject();

        $payload = LeenmoreServer::tool(FetchTool::class, [
            'id' => 'shareholder:'.$assignment->shareholder_id,
        ])->assertOk()->payload();

        $this->assertSame('901231-1******', $payload['registration_masked']);
        $this->assertSame('서울특별시 강남구', $payload['region']);

        $body = $this->flatten($payload);
        $this->assertStringNotContainsString(self::RRN, $body);
        $this->assertStringNotContainsString(self::PHONE, $body);
        $this->assertStringNotContainsString('테헤란로', $body);
    }

    public function test_a_per_project_contact_override_is_reported_but_never_disclosed(): void
    {
        $assignment = $this->shareholderOnAProject();
        $assignment->update(['contact_info' => '010-1111-2222', 'address' => '부산광역시 해운대구 우동 1234']);

        $body = $this->flatten(
            LeenmoreServer::tool(ListShareholdersTool::class, ['project_id' => $assignment->project_id])
                ->assertOk()->payload()
        );

        $this->assertStringNotContainsString('010-1111-2222', $body);
        $this->assertStringNotContainsString('우동 1234', $body);
        $this->assertStringContainsString('부산광역시 해운대구', $body);
    }

    public function test_activity_report_notes_never_appear_in_a_list(): void
    {
        $assignment = $this->shareholderOnAProject();

        Submission::factory()->forAssignment($assignment)->create([
            'note' => '연락처 010-5555-6666 로 다시 전화할 것',
            'contact' => self::PHONE,
        ]);

        $payload = LeenmoreServer::tool(ListSubmissionsTool::class, [
            'project_id' => $assignment->project_id,
        ])->assertOk()->payload();

        $body = $this->flatten($payload);
        $this->assertStringNotContainsString('010-5555-6666', $body);
        $this->assertStringNotContainsString(self::PHONE, $body);
        $this->assertArrayNotHasKey('note', $payload['data'][0]);
        $this->assertTrue($payload['data'][0]['has_contact']);
    }

    public function test_a_note_is_reachable_only_one_record_at_a_time_through_fetch(): void
    {
        $assignment = $this->shareholderOnAProject();
        $submission = Submission::factory()->forAssignment($assignment)->create([
            'note' => '재방문 필요',
            'contact' => self::PHONE,
        ]);

        $payload = LeenmoreServer::tool(FetchTool::class, [
            'id' => 'submission:'.$submission->id,
        ])->assertOk()->payload();

        $this->assertSame('재방문 필요', $payload['note']);

        // Even here the contact column itself stays behind the boolean.
        $this->assertTrue($payload['has_contact']);
        $this->assertStringNotContainsString(self::PHONE, $this->flatten($payload));
    }

    public function test_staff_email_and_phone_are_not_returned_by_the_user_tools(): void
    {
        $user = User::factory()->worker()->create([
            'name' => '김활동',
            'email' => 'worker@example.test',
            'phone' => self::PHONE,
        ]);

        foreach ([
            LeenmoreServer::tool(ListUsersTool::class)->assertOk()->payload(),
            LeenmoreServer::tool(FetchTool::class, ['id' => 'user:'.$user->id])->assertOk()->payload(),
            LeenmoreServer::tool(SearchTool::class, ['query' => '김활동'])->assertOk()->payload(),
        ] as $payload) {
            $body = $this->flatten($payload);
            $this->assertStringNotContainsString('worker@example.test', $body);
            $this->assertStringNotContainsString(self::PHONE, $body);
        }
    }

    public function test_no_password_or_two_factor_material_can_reach_a_response(): void
    {
        User::factory()->admin()->create(['name' => '관리자']);

        foreach ([
            LeenmoreServer::tool(ListUsersTool::class)->assertOk()->payload(),
            LeenmoreServer::tool(SearchTool::class, ['query' => '관리자'])->assertOk()->payload(),
        ] as $payload) {
            $body = $this->flatten($payload);

            foreach (['password', 'two_factor', 'remember_token'] as $secret) {
                $this->assertStringNotContainsString($secret, $body);
            }
        }
    }
}
