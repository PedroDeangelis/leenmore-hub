<?php

namespace Tests\Feature\Api;

use App\Livewire\Api\Docs;
use App\Livewire\Api\Tokens;
use App\Models\ApiToken;
use App\Models\Project;
use App\Models\User;
use App\Support\ApiCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The in-hub API portal: who can reach it, and that token minting / blocking /
 * deleting and the endpoint tester behave. The REST layer itself is covered by
 * ApiEndpointsTest and ApiTokenAuthTest.
 */
class ApiPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_the_api_tab(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('api.tokens'))->assertOk();
        $this->actingAs(User::factory()->office()->create())->get(route('api.tokens'))->assertForbidden();
        $this->actingAs(User::factory()->worker()->create())->get(route('api.tokens'))->assertRedirect(route('worker.dashboard'));
    }

    public function test_creating_a_token_reveals_the_plaintext_and_stores_hash_plus_encrypted_copy(): void
    {
        $admin = User::factory()->admin()->create();

        $component = Livewire::actingAs($admin)->test(Tokens::class)
            ->set('name', '회계법인 김앤장')
            ->call('create')
            ->assertHasNoErrors();

        $plain = $component->get('plainText');
        $this->assertNotNull($plain);
        $this->assertStringStartsWith('lmh_', $plain);

        $token = ApiToken::first();
        $this->assertSame('회계법인 김앤장', $token->name);
        $this->assertSame($admin->id, $token->created_by);
        $this->assertSame(hash('sha256', $plain), $token->token_hash);

        // Viewable again later, but never plaintext at rest.
        $this->assertSame($plain, $token->token);
        $this->assertNotSame($plain, $token->getRawOriginal('token'));

        // The post-creation reveal is cleared when dismissed.
        $component->call('dismissPlainText')->assertSet('plainText', null);
    }

    public function test_clicking_a_token_reveals_its_full_plaintext_and_clicking_again_hides_it(): void
    {
        $admin = User::factory()->admin()->create();
        ['token' => $token, 'plainText' => $plain] = ApiToken::generate('Client', $admin->id);

        Livewire::actingAs($admin)->test(Tokens::class)
            ->assertDontSee($plain)
            ->call('reveal', $token->id)
            ->assertSet('revealedId', $token->id)
            ->assertSee($plain)
            ->call('reveal', $token->id)
            ->assertSet('revealedId', null)
            ->assertDontSee($plain);
    }

    public function test_a_legacy_token_without_a_stored_plaintext_stays_masked(): void
    {
        $admin = User::factory()->admin()->create();
        ['token' => $token] = ApiToken::generate('Client', $admin->id);
        $token->update(['token' => null]);

        Livewire::actingAs($admin)->test(Tokens::class)
            ->assertSee('lmh_…'.$token->last_four)
            ->call('reveal', $token->id)
            ->assertOk();
    }

    public function test_creating_a_token_requires_a_label(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())->test(Tokens::class)
            ->set('name', '')
            ->call('create')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_blocking_a_token_revokes_it_without_deleting(): void
    {
        $admin = User::factory()->admin()->create();
        ['token' => $token] = ApiToken::generate('Client', $admin->id);

        Livewire::actingAs($admin)->test(Tokens::class)->call('revoke', $token->id);

        $this->assertNotNull($token->fresh()->revoked_at);
        $this->assertDatabaseHas('api_tokens', ['id' => $token->id]);
    }

    public function test_deleting_a_token_removes_it(): void
    {
        $admin = User::factory()->admin()->create();
        ['token' => $token] = ApiToken::generate('Client', $admin->id);

        Livewire::actingAs($admin)->test(Tokens::class)->call('delete', $token->id);

        $this->assertDatabaseMissing('api_tokens', ['id' => $token->id]);
    }

    public function test_an_unknown_endpoint_docs_page_is_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('api.docs', 'not-an-endpoint'))
            ->assertNotFound();
    }

    public function test_the_endpoint_tester_runs_the_query_and_returns_masked_json(): void
    {
        $admin = User::factory()->admin()->create();
        Project::factory()->create(['title' => 'Spring meeting']);

        Livewire::actingAs($admin)->test(Docs::class, ['endpoint' => 'projects'])
            ->call('run')
            ->assertSet('resultStatus', 200)
            ->assertSee('Spring meeting');
    }

    public function test_the_tester_days_repeater_runs_and_joins_rows_into_a_comma_list(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        $component = Livewire::actingAs($admin)->test(Docs::class, ['endpoint' => 'activity-data'])
            ->set('params.project', (string) $project->id)
            ->call('addListItem', 'days')
            ->call('addListItem', 'days')
            ->call('addListItem', 'days')
            ->set('params.days.0', '2026-03-01')
            ->set('params.days.1', '2026-03-02')
            // Row 2 left blank — dropped from the query, not sent as an empty day.
            ->call('run')
            ->assertHasNoErrors()
            ->assertSet('resultStatus', 200);

        // The curl example documents the comma-separated form of what was entered.
        $this->assertStringContainsString('days=2026-03-01%2C2026-03-02', $component->html());

        // Removing a row reindexes the remaining ones.
        $component->call('removeListItem', 'days', 0)
            ->assertSet('params.days', ['2026-03-02', '']);
    }

    public function test_the_tester_reports_a_404_for_a_missing_project(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())->test(Docs::class, ['endpoint' => 'project'])
            ->set('params.project', '999999')
            ->call('run')
            ->assertSet('resultStatus', 404);
    }

    public function test_every_catalog_endpoint_has_a_reachable_docs_page(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (array_keys(ApiCatalog::all()) as $slug) {
            $this->actingAs($admin)->get(route('api.docs', $slug))->assertOk();
        }
    }
}
