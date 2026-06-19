<?php

namespace Tests\Feature\Shareholders;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareholderTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_download_the_sample_template(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('shareholders.template'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('실명번호', $content);
        $this->assertStringContainsString('주주명', $content);
        $this->assertStringContainsString('전자위임날짜', $content);
    }

    public function test_office_users_can_download_the_sample_template(): void
    {
        $office = User::factory()->office()->create();

        $this->actingAs($office)
            ->get(route('shareholders.template'))
            ->assertOk();
    }

    public function test_workers_cannot_download_the_sample_template(): void
    {
        $worker = User::factory()->worker()->create();

        $this->actingAs($worker)
            ->get(route('shareholders.template'))
            ->assertRedirect(route('worker.dashboard'));
    }
}
