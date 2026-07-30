<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SetUserLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetUserLocale::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'api.token' => AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The MCP endpoint (routes/ai.php) speaks JSON-RPC. An unauthenticated client
        // must get a 401 — which carries the WWW-Authenticate header pointing at the
        // OAuth metadata — rather than a redirect to the HTML login page. That header
        // is how Claude and ChatGPT discover they need to run the OAuth flow, so a
        // redirect here would leave a connector unable to authenticate at all.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('mcp/*'),
        );
    })->create();
