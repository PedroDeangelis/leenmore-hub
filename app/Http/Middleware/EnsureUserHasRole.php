<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Usage: ->middleware('role:admin,office')
     *
     * Users from the other area are redirected to their own home
     * instead of receiving a 403.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $allowed = array_map(UserRole::from(...), $roles);

        if (in_array($user->role, $allowed, true)) {
            return $next($request);
        }

        return redirect()->route(
            $user->role->worksInAdminArea() ? 'dashboard' : 'worker.dashboard'
        );
    }
}
