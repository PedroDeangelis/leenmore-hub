# Auth & Roles Plan

Scope: **auth, users, roles, login only.** No project/shareholder migrations, no feature pages. Plan only — no code yet.

## Current Fortify state (reviewed)

- Login/2FA/passkeys/password-reset fully scaffolded; views map to Livewire auth pages ([app/Providers/FortifyServiceProvider.php](../app/Providers/FortifyServiceProvider.php)).
- Post-login redirect is the static `'home' => '/dashboard'` ([config/fortify.php](../config/fortify.php) line 76) — same for everyone; no role logic anywhere yet.
- `users` table has no role column ([database/migrations/0001_01_01_000000_create_users_table.php](../database/migrations/0001_01_01_000000_create_users_table.php)).
- Public registration is **enabled** (`Features::registration()` — Chisel-marked). For an internal tool with admin-created accounts this should be turned off when we implement (registration was admin-only in the legacy app too: `leenmore-app/src/pages/Admin/AddNewUser/`).
- A stale draft migration exists from an earlier session: [database/migrations/2026_06_11_000001_add_role_fields_to_users_table.php](../database/migrations/2026_06_11_000001_add_role_fields_to_users_table.php) — it predates this plan (no `office`, no `deactivated_at`) and should be **rewritten, not kept** when we implement.

## Roles

| Role | Area / layout | Access |
|---|---|---|
| `admin` | Admin area (`/dashboard`, admin layout) | everything |
| `office` | **Same admin area and layout** | admin area minus admin-only permissions |
| `worker` | Worker portal (`/app`, worker layout) | worker portal only |

Legacy mapping: Supabase `profiles.role` only has `admin`/`worker` — `office` is new. Import maps roles 1:1; office users get assigned by an admin afterwards.

## 1. Users table role field

Simplest thing that works — **one string column, no permission tables, no package** (three fixed roles don't justify spatie/permission):

- `role` — string(20), required, default `'worker'`, values backed by a PHP enum `App\Enums\UserRole` (`Admin`, `Office`, `Worker`), cast on the `User` model.
- Helpers on the enum/model: `isAdmin()`, `isOffice()`, `worksInAdminArea()` (admin OR office).
- Same migration also adds the already-agreed profile fields so we only touch `users` once: `phone`, `email_receiver`, `deactivated_at`, `supabase_uuid` (per [FINAL_DATABASE_SCHEMA_PROPOSAL.md](FINAL_DATABASE_SCHEMA_PROPOSAL.md) §1).

## 2. Login redirect by role

Fortify resolves the post-login destination through bindable response contracts — the clean hook is binding two small classes in `FortifyServiceProvider::register()`:

- `Laravel\Fortify\Contracts\LoginResponse` → admin/office to `/dashboard`, worker to `/app`.
- `Laravel\Fortify\Contracts\TwoFactorLoginResponse` → same logic (2FA logins bypass the plain LoginResponse).

`config/fortify.php` `'home'` stays `/dashboard` as the fallback for other flows (e.g. password reset). Friendly cross-area handling: a worker who manually opens `/dashboard` gets redirected to `/app` (not a bare 403), and vice versa — handled in the role middleware below.

## 3. Middleware vs policy — use both, in layers

- **Middleware for area access (coarse):** one parameterized middleware `EnsureUserHasRole` registered as alias `role` in [bootstrap/app.php](../bootstrap/app.php) — usage `role:admin,office`. Redirects (per above) instead of aborting when the user simply belongs to the other area; aborts 403 otherwise.
- **Gates for permissions (fine):** a small set of string abilities defined in `AppServiceProvider::boot()` from one permission map (single source of truth, see §6). No DB tables; a gate is `Gate::define('manage-users', fn (User $u) => $u->isAdmin())`.
- **Policies later:** once domain models exist (Phase: projects/receipts), model policies can delegate to the same gates. Not needed for the auth-only milestone.

## 4. Route groups

```text
routes/web.php          → keeps '/', settings; requires the two files below
routes/admin.php        → prefix /dashboard, name admin.*, middleware ['auth', 'role:admin,office']
                          └─ nested admin-only group: middleware ['can:manage-users'] etc. (or 'role:admin')
routes/worker.php       → prefix /app, name worker.*, middleware ['auth', 'role:worker']
```

- For this milestone the groups contain only a placeholder dashboard page each (real pages come in later phases).
- Existing starter route `Route::view('dashboard', ...)` in [routes/web.php](../routes/web.php) is replaced by the admin group's dashboard.
- Open point: should `admin` also be allowed into the worker portal (useful for support/debugging)? Default in this plan: **no** — one role, one area; revisit if needed.

## 5. Hiding restricted menu items from office users

- Sidebar items in the admin layout wrapped in `@can('<ability>')` — e.g. the **Users** nav item renders only inside `@can('manage-users')`. Flux sidebar items are plain Blade, so `@can` wraps them cleanly.
- Hiding is cosmetic only — the same abilities are enforced server-side: route middleware (`can:` on admin-only routes) + `$this->authorize()` in the Livewire components when they're built. Office users never gain access by guessing URLs.

## 6. Permission map: admin-only vs office-accessible

One PHP map (constant on `UserRole` or a `config/permissions.php`) drives both the Gates and the menu. Proposed split, based on what the React admin exposes (**confirm with client before implementation** — `office` doesn't exist in the legacy app, so this is a product decision):

| Ability (gate) | admin | office | Covers |
|---|---|---|---|
| `access-admin-area` | ✅ | ✅ | the `/dashboard` area itself |
| `manage-users` | ✅ | ❌ | user CRUD, role changes, deactivation (`/dashboard/user…`) |
| `manage-projects` | ✅ | ❌ | create/delete projects, edit results/link_manage_id |
| `manage-settings` | ✅ | ❌ | receipt deadline lock, usage-history options (`Receipts` admin widgets) |
| `send-worker-emails` | ✅ | ❌ | email-to-worker compose/send + worker reports |
| `bulk-delete` | ✅ | ❌ | receipt bulk delete and similar destructive batch actions |
| `view-projects` / `view-submissions` / `view-receipts` / `view-activity-data` | ✅ | ✅ | all read/review screens, search, attachment preview |
| `edit-submissions` | ✅ | ✅ *(confirm)* | create/edit/soft-delete submissions on behalf of workers |
| `manage-resources` | ✅ | ✅ *(confirm)* | upload/reorder project resources |
| `export-data` | ✅ | ✅ *(confirm)* | Excel/CSV/zip downloads |

Worker portal needs no gates — area middleware suffices; workers only ever see their own data (enforced in queries when those pages are built).

## Implementation order (when approved — small, ~9 files)

1. Rewrite the users migration (role + profile fields, replaces the stale draft).
2. `App\Enums\UserRole` + `User` model cast/helpers.
3. `EnsureUserHasRole` middleware + `role` alias in `bootstrap/app.php`.
4. `LoginResponse` + `TwoFactorLoginResponse` bindings in `FortifyServiceProvider`.
5. Permission map + `Gate::define()` loop in `AppServiceProvider`.
6. `routes/admin.php` + `routes/worker.php` with placeholder dashboards; disable public registration (Chisel registration feature off).
7. Seeder/factory states for the three roles (testing).

Out of scope for this milestone: data import, real admin/worker pages, project tables, office-user assignment UI.
