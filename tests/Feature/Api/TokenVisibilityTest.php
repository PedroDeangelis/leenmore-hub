<?php

namespace Tests\Feature\Api;

use App\Livewire\Api\Docs;
use App\Livewire\Api\Tokens;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A token is a master key to the data, so who can see one is a security
 * boundary, not a convenience. An admin sees every key; a viewer sees only the
 * keys assigned to them, and cannot mint, block or delete anything.
 */
class TokenVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_viewer_sees_only_their_own_assigned_token(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->viewer()->create();

        ['token' => $theirs] = ApiToken::generate('Assigned to the viewer', $admin->id, null, $viewer->id);
        ['token' => $someoneElses] = ApiToken::generate('Belongs to an outside client', $admin->id);

        $this->actingAs($viewer);

        // The other client's label and the last four of its key are both absent;
        // the modal's placeholder text is not a listing, so match on the row data.
        Livewire::test(Tokens::class)
            ->assertSee('Assigned to the viewer')
            ->assertDontSee('Belongs to an outside client')
            ->assertDontSee($someoneElses->last_four);

        // And the tester may only ever curl with their own key.
        Livewire::test(Docs::class, ['endpoint' => 'projects'])
            ->assertSet('tokenId', $theirs->id)
            ->assertDontSee($someoneElses->last_four);
    }

    public function test_an_admin_sees_every_token(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->viewer()->create();

        ApiToken::generate('Assigned to the viewer', $admin->id, null, $viewer->id);
        ApiToken::generate('Belongs to an outside client', $admin->id);

        Livewire::actingAs($admin)
            ->test(Tokens::class)
            ->assertSee('Assigned to the viewer')
            ->assertSee('Belongs to an outside client');
    }

    public function test_a_viewer_with_no_assigned_token_gets_the_placeholder(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->viewer()->create();

        ApiToken::generate('Someone else entirely', $admin->id);

        Livewire::actingAs($viewer)
            ->test(Docs::class, ['endpoint' => 'projects'])
            ->assertSet('tokenId', null)
            ->assertSee('YOUR_TOKEN');
    }

    public function test_a_viewer_cannot_mint_block_or_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->viewer()->create();

        ['token' => $token] = ApiToken::generate('Belongs to an outside client', $admin->id);

        Livewire::actingAs($viewer)
            ->test(Tokens::class)
            ->set('name', 'Minted by a viewer')
            ->call('create')
            ->assertForbidden();

        Livewire::actingAs($viewer)->test(Tokens::class)->call('revoke', $token->id)->assertForbidden();
        Livewire::actingAs($viewer)->test(Tokens::class)->call('delete', $token->id)->assertForbidden();

        $this->assertDatabaseHas('api_tokens', ['id' => $token->id, 'revoked_at' => null]);
        $this->assertDatabaseMissing('api_tokens', ['name' => 'Minted by a viewer']);
    }

    public function test_a_viewer_cannot_reveal_a_token_by_tampering_with_the_id(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->viewer()->create();

        ['token' => $someoneElses] = ApiToken::generate('Belongs to an outside client', $admin->id);

        Livewire::actingAs($viewer)
            ->test(Tokens::class)
            ->call('reveal', $someoneElses->id)
            ->assertSet('revealedId', null);
    }
}
