<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * The OAuth consent screen is the only hub page a user sees while connecting an
 * assistant, and it is reached through Passport rather than a route of ours — so
 * nothing else would catch it breaking. It must name the client, identify the
 * account, and say plainly that access is read-only and redacted.
 */
class ConsentScreenTest extends TestCase
{
    use RefreshDatabase;

    private function render(string $clientName = 'Claude'): string
    {
        $client = new Client;
        $client->name = $clientName;

        return view('mcp.authorize', [
            'client' => $client,
            'user' => User::factory()->admin()->create(['email' => 'admin@leenmore.test']),
            'scopes' => [],
            'authToken' => 'test-auth-token',
            'request' => request(),
        ])->render();
    }

    public function test_it_names_the_client_and_the_signed_in_account(): void
    {
        $html = $this->render('ChatGPT');

        $this->assertStringContainsString('ChatGPT', $html);
        $this->assertStringContainsString('admin@leenmore.test', $html);
    }

    public function test_it_states_that_access_is_read_only_and_redacted(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('Nothing can be created, changed or deleted.', $html);
        $this->assertStringContainsString('실명번호 is always masked', $html);
    }

    public function test_it_posts_the_auth_token_to_both_the_approve_and_deny_routes(): void
    {
        $html = $this->render();

        // Only auth_token is read back — Passport pulls the pending request from the
        // session — so if this input is lost, approving fails with an invalid token.
        $this->assertSame(2, substr_count($html, 'name="auth_token" value="test-auth-token"'));
        $this->assertStringContainsString(route('passport.authorizations.approve'), $html);
        $this->assertStringContainsString(route('passport.authorizations.deny'), $html);
    }
}
