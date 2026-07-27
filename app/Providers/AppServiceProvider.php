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
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Ability => roles allowed. Single source of truth for Gates and menu
     * visibility (@can in Blade). See audit/AUTH_ROLES_PLAN.md §6.
     */
    private const PERMISSIONS = [
        'access-admin-area' => [UserRole::Admin, UserRole::Office],
        'manage-users' => [UserRole::Admin],
        'manage-projects' => [UserRole::Admin],
        'manage-settings' => [UserRole::Admin],
        'send-worker-emails' => [UserRole::Admin],
        'bulk-delete' => [UserRole::Admin],
        'view-projects' => [UserRole::Admin, UserRole::Office],
        'view-submissions' => [UserRole::Admin, UserRole::Office],
        'view-receipts' => [UserRole::Admin, UserRole::Office],
        'manage-receipts' => [UserRole::Admin, UserRole::Office],
        'view-activity-data' => [UserRole::Admin, UserRole::Office],
        'edit-submissions' => [UserRole::Admin, UserRole::Office],
        'manage-shareholders' => [UserRole::Admin, UserRole::Office],
        'manage-resources' => [UserRole::Admin, UserRole::Office],
        'export-data' => [UserRole::Admin, UserRole::Office],
        // Connect an external AI assistant to the read-only MCP server (routes/ai.php).
        // Revoking AI access for a role is a one-line change here.
        'use-mcp' => [UserRole::Admin],
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
        $this->configureMcp();
    }

    /**
     * Wire up the read-only MCP server that external AI assistants connect to.
     * The server itself is registered in routes/ai.php.
     */
    protected function configureMcp(): void
    {
        // Passport renders our own consent screen rather than its packaged one, so the
        // page a user is asked to approve on is visibly the hub.
        Passport::authorizationView(fn (array $parameters) => view('mcp.authorize', $parameters));

        // AI clients fan out tool calls quickly when answering one question. Per-user
        // where a token is attached, per-IP for unauthenticated handshake attempts.
        RateLimiter::for('mcp', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));
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

        // Admins set passwords on behalf of 활동가/office staff, so keep the bar low and
        // identical in every environment — length only, no complexity or breach check.
        Password::defaults(fn (): Password => Password::min(6));
    }
}
