<?php

namespace Tests\Feature\Mcp;

use Laravel\Mcp\Server\Testing\TestResponse;
use Tests\TestCase;

/**
 * Shared setup for the MCP tests. The package keeps a tool response's decoded
 * payload behind a protected accessor, so register a macro once — Macroable binds
 * the closure to the response instance, which is what gives it access.
 */
abstract class McpTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestResponse::macro('payload', function (): array {
            /** @var TestResponse $this */
            // @phpstan-ignore-next-line — bound to the instance by Macroable.
            return $this->structuredContent() ?? [];
        });
    }

    /**
     * Every string in a tool's response, for assertions about what must never leak.
     */
    protected function flatten(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
