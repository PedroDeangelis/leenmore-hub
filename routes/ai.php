<?php

use App\Mcp\Servers\LeenmoreServer;
use Laravel\Mcp\Facades\Mcp;

/*
 * The read-only MCP server external AI assistants (Claude, ChatGPT, Gemini) connect
 * to. One endpoint serves all of them — MCP is the shared standard, so there is no
 * per-vendor integration here. See docs/mcp.md for client setup.
 *
 * oauthRoutes() publishes the OAuth 2.1 discovery documents and the dynamic client
 * registration endpoint, which is what lets a client configure itself from the URL
 * alone. Claude.ai and ChatGPT's connector UIs only speak OAuth — neither has a field
 * for a static token — so this, rather than a Sanctum bearer token, is what makes the
 * browser chat apps work at all.
 */
Mcp::oauthRoutes();

Mcp::web('/mcp/leenmore', LeenmoreServer::class)
    ->middleware(['auth:api', 'can:use-mcp', 'throttle:mcp']);
