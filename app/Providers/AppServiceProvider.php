<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        'view-activity-data' => [UserRole::Admin, UserRole::Office],
        'edit-submissions' => [UserRole::Admin, UserRole::Office],
        'manage-shareholders' => [UserRole::Admin, UserRole::Office],
        'manage-resources' => [UserRole::Admin, UserRole::Office],
        'export-data' => [UserRole::Admin, UserRole::Office],
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

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
