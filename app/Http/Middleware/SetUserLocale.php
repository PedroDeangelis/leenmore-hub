<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    public const SUPPORTED = ['ko', 'en'];

    /**
     * Logged-in users get their saved language; guests get the app default (ko).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? config('app.locale');

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
