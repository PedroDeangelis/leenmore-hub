# Leenmore Hub — project conventions

Laravel 13 · PHP 8.3 · Livewire 4 · Tailwind CSS v4 (Livewire starter kit).

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

Roles live in `App\Enums\UserRole` (`admin`, `office`, `worker`). Per-ability Gates are defined
from the single `PERMISSIONS` map in `app/Providers/AppServiceProvider.php` — add new abilities
there and gate routes with `->middleware('can:<ability>')` and menu items with `@can('<ability>')`.

## MCP server — read-only, and PII never leaves un-redacted

`app/Mcp/` exposes hub data to external AI assistants (ChatGPT, Claude, Gemini) over one shared
MCP endpoint, registered in `routes/ai.php` behind `auth:api` + `can:use-mcp`. See
[docs/mcp.md](docs/mcp.md).

Two rules when touching it:

- **Read-only.** Every tool carries `#[IsReadOnly]` and must not write. A test globs
  `app/Mcp/Tools/` and fails if the annotation is missing, so a write tool can't slip in.
- **Route personal data through `App\Support\Pii`.** Tool responses are transmitted to OpenAI /
  Anthropic / Google and may be retained there, so 실명번호 is masked, contacts and full addresses
  are never sent, and free-text notes stay out of list tools.
  `tests/Feature/Mcp/RedactionTest.php` is the guard on this — extend it when adding a tool.

## Tests & formatting

- Run the suite with `php artisan test`.
- Format with `vendor/bin/pint` (check-only: `vendor/bin/pint --test`).
