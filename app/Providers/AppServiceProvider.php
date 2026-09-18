<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Ability => roles allowed. Single source of truth for Gates and menu
     * visibility (@can in Blade). See audit/AUTH_ROLES_PLAN.md §6.
     */
    private const PERMISSIONS = [
        // Read the API docs and test an endpoint. Viewers get this so they can
        // work against the API; what a viewer may test with is narrowed further
        // by token assignment (see App\Livewire\Api\Docs::selectableTokens()).
        'use-api-portal' => [UserRole::Admin, UserRole::Viewer],
        // Mint / revoke bearer tokens. A token is a master key to the data, so
        // minting stays with admins.
        'manage-api-tokens' => [UserRole::Admin],
        // Create and edit the login accounts for this portal.
        'manage-users' => [UserRole::Admin],
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePermissions();
        $this->configureApi();
    }

    /**
     * Rate-limit the read-only REST API (routes/api.php). Keyed by the presented
     * bearer token where there is one, so a single client's calls throttle against
     * that client, not the whole API.
     */
    protected function configureApi(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($request->bearerToken() ?: $request->ip()));
    }

    /**
     * Register one Gate per ability in the permission map.
     */
    protected function configurePermissions(): void
    {
        foreach (self::PERMISSIONS as $ability => $roles) {
            Gate::define($ability, fn (User $user): bool => in_array($user->role, $roles, true));
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Admins set passwords on behalf of everyone else, so keep the bar low and
        // identical in every environment — length only, no complexity or breach check.
        Password::defaults(fn (): Password => Password::min(6));
    }
}
