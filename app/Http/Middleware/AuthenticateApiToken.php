<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the read-only REST API (routes/api.php) with a bearer token minted on
 * the API tab. A plain first-party bearer token: no OAuth, no session, nothing
 * for an outside developer to negotiate beyond sending the header.
 *
 * The matched token is bound to the request as `api_token` for logging; there is
 * no acting user, because every endpoint returns the same masked, read-only view.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainText = $request->bearerToken();

        $token = $plainText === null || $plainText === ''
            ? null
            : ApiToken::findActiveByPlainText($plainText);

        if ($token === null) {
            return $this->unauthorized();
        }

        // Best-effort last-used stamp; never let it block or slow the request.
        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('api_token', $token);

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'message' => 'Invalid or missing API token.',
        ], 401, ['WWW-Authenticate' => 'Bearer realm="leenmore-api"']);
    }
}
