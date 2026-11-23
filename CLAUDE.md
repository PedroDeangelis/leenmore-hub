# Leenmore Hub — project conventions

Laravel 13 · PHP 8.3 · Livewire 4 · Tailwind CSS v4.

A read-only REST API over the client's Supabase database, plus a small bilingual (한국어/English)
portal for its documentation, its bearer tokens, and the accounts that use them. Nothing else —
the projects/shareholders/receipts/worker screens this app once had were removed when the client
chose to keep their existing website.

## Icons — use Blade Icons, never hand-write `<svg>`

This project uses [Blade Icons](https://github.com/blade-ui-kit/blade-icons) with the
[Heroicons](https://github.com/blade-ui-kit/blade-heroicons) set. **Do not paste raw inline
`<svg>` markup for UI icons.** Use the Blade components instead.

```blade
{{-- outline (default style used across the app) --}}
<x-heroicon-o-home class="size-5" />

{{-- solid / mini / micro --}}
<x-heroicon-s-user class="size-5" />
<x-heroicon-m-x-mark class="size-4" />
<x-heroicon-c-check class="size-3" />
```

- **Sizing & color:** pass Tailwind classes via `class` — the icon renders an `<svg>` and any
  attributes (`class`, `x-show`, `x-cloak`, `wire:click`, …) are forwarded onto it. Example:
  `<x-heroicon-o-users class="size-5 text-white/80" />`.
- **Component naming:** `heroicon-{o|s|m|c}-{name}`, where `name` is the kebab-case Heroicon name
  (e.g. `bars-3`, `x-mark`, `chevron-up-down`, `eye`, `eye-slash`, `magnifying-glass`).
- **Finding names:** browse [heroicons.com](https://heroicons.com) or
  `vendor/blade-ui-kit/blade-heroicons/resources/svg/` (a file `o-home.svg` → `<x-heroicon-o-home />`).
- **Need an icon not in Heroicons?** Install another Blade Icons set
  (e.g. a `codeat3/blade-*` package) rather than inlining SVG.
- **Exceptions (raw SVG is OK):** the brand logo
  (`resources/views/components/app-logo-icon.blade.php`) and decorative graphics such as
  `resources/views/components/placeholder-pattern.blade.php`. These are brand/decorative assets,
  not icon-set glyphs.

After adding or changing icons run `php artisan view:clear`. In production, `php artisan icons:cache`
generates the icon manifest for faster rendering (`php artisan icons:clear` to remove it).

## Authorization

Roles live in `App\Enums\UserRole` (`admin`, `viewer`). Per-ability Gates are defined from the single
`PERMISSIONS` map in `app/Providers/AppServiceProvider.php` — add new abilities there and gate routes
with `->middleware('can:<ability>')` and menu items with `@can('<ability>')`.

There are three abilities: `use-api-portal` (both roles), `manage-api-tokens` and `manage-users`
(admin only). A viewer may read the docs and test an endpoint, but only with an API key assigned to
them — see below.

## Supabase is the system of record, and we only read it

`app/Support/Supabase/` is the entire data layer. The client's own website owns this data; this app
never writes to it. Rules when touching it:

- **GET only.** `SupabaseClient` exposes no method that issues anything else, which is what makes
  read-only a property of the code rather than a promise. `SupabaseClientTest` asserts every request
  made across all six endpoints is a GET — keep that true.
- **Route personal data through `App\Support\Pii`.** 실명번호 is masked, contact numbers are never
  returned (only whether one exists), addresses are cut to their region, and the free-text
  `submission.note` leaves only through `Pii::note()`, which replaces phone numbers, 실명번호 and
  emails with placeholders — workers type phone numbers into it.
- **Never read a roster unpaginated.** `shareholder` holds ~1.45M rows; an unbounded select against
  it takes the API down rather than merely being slow.

Upstream has three habits that are easy to get wrong, each handled in one place:

| Habit | Handled by |
|---|---|
| Numbers are comma-formatted text (`"259,498,284"`; a plain `(int)` cast yields **259**) | `Supabase\Num` |
| `project.results` is a `text[]` of JSON *strings*, and a 판단 is referenced by **array index** (`"0"`), not name | `Supabase\ResultSet` |
| Workers are uuids on submissions/receipts but **names** on roster rows | `Supabase\ProfileRepository` |

`is_proxy` means the 판단's colour is `green` — true of all 639 green options across all 123 live
projects, every one a 위임 variant.

**`receipt.status` is not a delete flag.** Only 771 of 11,083 rows are true and the rest are ordinary
receipts; nothing filters on it and its meaning is still an open question for the client.

**The `audit/*.md` notes are unreliable** — they were wrong about `results`, `shareholder.user`,
`date_of_birth`, `email_receiver` and `receipt.status`. Verify against the live schema, not the dump.

## API tokens

A token is a master key to the data, so `api_tokens.assigned_to` decides who may see one:
`ApiToken::scopeVisibleTo()` narrows both the token list and the docs tester, and admins alone may
mint, block or delete. `tests/Feature/Api/TokenVisibilityTest.php` is the guard on that boundary —
extend it rather than loosening the scope.

## Tests & formatting

- Run the suite with `php artisan test`.
- Format with `vendor/bin/pint` (check-only: `vendor/bin/pint --test`).
