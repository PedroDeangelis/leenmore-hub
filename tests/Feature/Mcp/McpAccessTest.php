<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Who may reach the MCP endpoint. The tools themselves are read-only, but the data
 * behind them is not public, so the guard on routes/ai.php is what stands between
 * an AI client and the whole shareholder database.
 */
class McpAccessTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/mcp/leenmore';

    /**
     * A minimal JSON-RPC initialize call — enough to get past routing and be judged
     * by the middleware.
     *
     * @return array<string, mixed>
     */
    private function initialize(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['Accept' => 'application/json, text/event-stream'];
    }

    /**
     * The 401 body matters less than the header: `WWW-Authenticate` is how an MCP
     * client discovers it must run the OAuth flow and where the metadata lives. A
     * redirect to the HTML login page here would leave a connector unable to
     * authenticate at all, which is why bootstrap/app.php renders JSON for mcp/*.
     */
    public function test_an_unauthenticated_client_gets_a_401_challenge_not_a_login_redirect(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->initialize(), $this->headers())
            ->assertUnauthorized();

        $this->assertStringContainsString(
            '.well-known/oauth-protected-resource',
            (string) $response->headers->get('WWW-Authenticate'),
        );
    }

    public function test_an_admin_token_reaches_the_server(): void
    {
        Passport::actingAs(User::factory()->admin()->create(), ['mcp:use']);

        $this->postJson(self::ENDPOINT, $this->initialize(), $this->headers())
            ->assertOk();
    }

    public function test_office_and_worker_tokens_are_refused_by_the_use_mcp_gate(): void
    {
        foreach ([User::factory()->office()->create(), User::factory()->worker()->create()] as $user) {
            Passport::actingAs($user, ['mcp:use']);

            $this->postJson(self::ENDPOINT, $this->initialize(), $this->headers())
                ->assertForbidden();
        }
    }

    /**
     * Exercises the real JSON-RPC surface rather than calling tools directly, so a
     * malformed input schema — which only shows up during serialisation — fails here
     * instead of when a client tries to connect.
     */
    public function test_every_tool_and_resource_is_advertised_over_the_endpoint(): void
    {
        Passport::actingAs(User::factory()->admin()->create(), ['mcp:use']);

        $tools = $this->postJson(self::ENDPOINT, [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => [],
        ], $this->headers())->assertOk()->json('result.tools');

        $this->assertEqualsCanonicalizing([
            'list-projects', 'get-project', 'list-shareholders', 'list-submissions',
            'activity-data', 'list-receipts', 'list-users', 'search', 'fetch',
        ], array_column($tools, 'name'));

        foreach ($tools as $tool) {
            $this->assertSame('object', $tool['inputSchema']['type'], $tool['name'].' has no object input schema.');
            $this->assertNotEmpty($tool['description'], $tool['name'].' has no description for the model to read.');
        }

        $resources = $this->postJson(self::ENDPOINT, [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'resources/list',
            'params' => [],
        ], $this->headers())->assertOk()->json('result.resources');

        $this->assertSame('leenmore://glossary', $resources[0]['uri']);
    }

    public function test_the_endpoint_only_answers_post(): void
    {
        Passport::actingAs(User::factory()->admin()->create(), ['mcp:use']);

        // The MCP spec has clients POST; GET and DELETE exist purely to answer 405.
        $this->get(self::ENDPOINT)->assertMethodNotAllowed();
        $this->delete(self::ENDPOINT)->assertMethodNotAllowed();
    }

    public function test_oauth_discovery_documents_are_published_for_clients_to_find(): void
    {
        // This is what lets Claude and ChatGPT configure themselves from the URL alone.
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonStructure(['issuer', 'authorization_endpoint', 'token_endpoint', 'registration_endpoint']);

        $this->getJson('/.well-known/oauth-protected-resource')
            ->assertOk()
            ->assertJsonStructure(['resource', 'authorization_servers']);
    }

    public function test_use_mcp_is_an_admin_only_ability(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->can('use-mcp'));
        $this->assertFalse(User::factory()->office()->create()->can('use-mcp'));
        $this->assertFalse(User::factory()->worker()->create()->can('use-mcp'));
    }
}
