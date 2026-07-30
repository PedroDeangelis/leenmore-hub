<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The bearer-token gate on the REST API. The data behind it is not public, so
 * this middleware is the only thing between a token holder and the hub — every
 * way a token can be invalid must be a clean 401.
 */
class ApiTokenAuthTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_a_valid_token_reaches_the_api(): void
    {
        $token = ApiToken::generate('Client', $this->admin()->id)['plainText'];

        $this->getJson('/api/v1/projects', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonStructure(['total', 'data']);
    }

    public function test_a_missing_token_is_rejected(): void
    {
        $this->getJson('/api/v1/projects')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid or missing API token.');
    }

    public function test_a_garbage_token_is_rejected(): void
    {
        $this->getJson('/api/v1/projects', ['Authorization' => 'Bearer not-a-real-token'])
            ->assertUnauthorized();
    }

    public function test_a_revoked_token_is_rejected(): void
    {
        ['token' => $token, 'plainText' => $plain] = ApiToken::generate('Client', $this->admin()->id);
        $token->forceFill(['revoked_at' => now()])->save();

        $this->getJson('/api/v1/projects', ['Authorization' => 'Bearer '.$plain])
            ->assertUnauthorized();
    }

    public function test_an_expired_token_is_rejected(): void
    {
        ['plainText' => $plain] = ApiToken::generate('Client', $this->admin()->id, now()->subDay());

        $this->getJson('/api/v1/projects', ['Authorization' => 'Bearer '.$plain])
            ->assertUnauthorized();
    }

    public function test_using_a_token_stamps_last_used_at(): void
    {
        ['token' => $token, 'plainText' => $plain] = ApiToken::generate('Client', $this->admin()->id);
        $this->assertNull($token->last_used_at);

        $this->getJson('/api/v1/projects', ['Authorization' => 'Bearer '.$plain])->assertOk();

        $this->assertNotNull($token->fresh()->last_used_at);
    }

    public function test_the_plaintext_is_never_stored(): void
    {
        ['plainText' => $plain] = ApiToken::generate('Client', $this->admin()->id);

        // Only the hash and last four are persisted; the plaintext must not appear.
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $plain]);
        $this->assertSame(hash('sha256', $plain), ApiToken::first()->token_hash);
    }
}
