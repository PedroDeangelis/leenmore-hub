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

    public function test_both_roles_can_open_the_api_tab(): void
    {
        // Viewers are here to collect and use a key, so the page itself is open
        // to them; what they may see and do on it is narrowed by role instead
        // (see TokenVisibilityTest).
        $this->actingAs(User::factory()->admin()->create())->get(route('api.tokens'))->assertOk();
        $this->actingAs(User::factory()->viewer()->create())->get(route('api.tokens'))->assertOk();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('api.tokens'))->assertRedirect(route('login'));
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

    public function test_the_curl_example_follows_the_tester_fields_without_running_the_request(): void
    {
        $admin = User::factory()->admin()->create();

        $component = Livewire::actingAs($admin)->test(Docs::class, ['endpoint' => 'projects'])
            ->set('params.search', 'Spring');

        // Updated by the field alone — no 'run' call, so no validation errors and
        // no response rendered yet.
        $this->assertStringContainsString('search=Spring', $component->html());
        $component->assertHasNoErrors()->assertSet('result', null);
    }

    public function test_a_path_param_typed_into_the_tester_lands_in_the_curl_url(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        $component = Livewire::actingAs($admin)->test(Docs::class, ['endpoint' => 'project'])
            ->set('params.project', (string) $project->id);

        // The id lands in the curl URL; the unfilled {project} placeholder survives
        // only in the static path shown in the page header.
        $this->assertStringContainsString('/api/v1/projects/'.$project->id, $component->html());
    }

    public function test_the_curl_example_offers_a_jq_pipe_that_is_on_by_default(): void
    {
        $html = Livewire::actingAs(User::factory()->admin()->create())
            ->test(Docs::class, ['endpoint' => 'projects'])->html();

        // Toggled client-side by Alpine, and inside the copied element so the
        // pipe travels with the command.
        $this->assertStringContainsString('x-model="jq"', $html);
        $this->assertStringContainsString('checked', $html);
        $this->assertStringContainsString('<span x-show="jq"> | jq</span></code></pre>', $html);
        $this->assertStringContainsString('jq: true', $html);
    }

    public function test_the_newest_usable_token_is_preselected(): void
    {
        $admin = User::factory()->admin()->create();
        ['plainText' => $older] = ApiToken::generate('Older', $admin->id);
        ['token' => $newest, 'plainText' => $newer] = ApiToken::generate('Newer', $admin->id);

        Livewire::actingAs($admin)->test(Docs::class, ['endpoint' => 'projects'])
            ->assertSet('tokenId', $newest->id)
            ->assertSee('Bearer '.$newer)
            ->assertDontSee($older)
            ->assertDontSee('Bearer YOUR_TOKEN');
    }

    public function test_the_curl_example_falls_back_to_the_placeholder_with_no_usable_tokens(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())->test(Docs::class, ['endpoint' => 'projects'])
            ->assertSet('tokenId', null)
            ->assertSee('Bearer YOUR_TOKEN');
    }

    public function test_the_picker_can_be_set_back_to_the_placeholder(): void
    {
        $admin = User::factory()->admin()->create();
        ['plainText' => $plain] = ApiToken::generate('Client', $admin->id);

        Livewire::actingAs($admin)->test(Docs::class, ['endpoint' => 'projects'])
            ->assertSee('Bearer '.$plain)
            ->set('tokenId', null)
            ->assertSee('Bearer YOUR_TOKEN')
            ->assertDontSee($plain);
    }

    public function test_the_token_picker_offers_only_usable_tokens(): void
    {
        $admin = User::factory()->admin()->create();

        ['token' => $usable] = ApiToken::generate('Usable', $admin->id);
        ['token' => $revoked] = ApiToken::generate('Revoked', $admin->id);
        $revoked->update(['revoked_at' => now()]);
        ['token' => $expired] = ApiToken::generate('Expired', $admin->id);
        $expired->forceFill(['expires_at' => now()->subDay()])->save();
        ['token' => $legacy] = ApiToken::generate('Legacy', $admin->id);
        $legacy->update(['token' => null]);

        $html = Livewire::actingAs($admin)->test(Docs::class, ['endpoint' => 'projects'])->html();

        $this->assertStringContainsString('Usable', $html);
        $this->assertStringNotContainsString('Revoked', $html);
        $this->assertStringNotContainsString('Expired', $html);
        $this->assertStringNotContainsString('Legacy', $html);
        $this->assertStringContainsString('value="'.$usable->id.'"', $html);
    }

    public function test_picking_an_unusable_token_falls_back_to_the_placeholder(): void
    {
        $admin = User::factory()->admin()->create();
        ['token' => $revoked, 'plainText' => $plain] = ApiToken::generate('Revoked', $admin->id);
        $revoked->update(['revoked_at' => now()]);

        Livewire::actingAs($admin)->test(Docs::class, ['endpoint' => 'projects'])
            ->set('tokenId', $revoked->id)
            ->assertSee('Bearer YOUR_TOKEN')
            ->assertDontSee($plain);
    }

    public function test_every_catalog_endpoint_has_a_reachable_docs_page(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (array_keys(ApiCatalog::all()) as $slug) {
            $this->actingAs($admin)->get(route('api.docs', $slug))->assertOk();
        }
    }
}
