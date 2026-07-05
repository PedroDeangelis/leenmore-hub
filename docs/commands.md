# Commands

All the console commands available in Leenmore Hub: our own artisan commands, the
scheduled tasks, and the composer/Lando shortcuts used day-to-day.

> **How to run:** the app runs on Lando, so from the host use `lando artisan <command>`
> (Lando's `artisan` tooling proxies to `php artisan` inside the appserver container).
> Plain `php artisan` only works inside the container — from the host it cannot reach
> the `database` service.

---

## Custom artisan commands

### `esignon:sync`

Pulls completed ESIGNON (전자위임 / e-proxy) documents from `docs.esignon.net` and writes
them onto the shareholder roster. The native replacement for the retired
`leenmore-storage` ESIGNON pipeline.

```bash
lando artisan esignon:sync                 # every active project with an ESIGNON ID
lando artisan esignon:sync --project=12    # one project (any status) — for testing
```

What one run does, per project with a `link_manage_id` (the "ESIGNON ID" on the
project page):

1. **Pull** — logs into the ESIGNON API (token cached until it expires), downloads the
   link's completed documents, and upserts them into the `esignon_shareholders` table.
   Unchanged rows (same data hash) are skipped; new or changed rows are flagged
   `needs_sync`.
2. **Match** — each flagged signer is matched to a roster row by **name + 생년월일
   6자리** (`shareholders.name` + `date_of_birth_code`). Matches get
   `api_recipient_contact` + `api_recipient_completion_date` written onto
   `project_shareholders` (the 전자위임연락처 / 전자위임날짜 columns); the signer is then
   marked synced. Signers with no roster match stay flagged — they are the
   **주주명부 누락** set shown on the project page and in the activity-log Excel's
   second sheet.

Output is one line per project (`pulled / matched / missing`); a failing project is
logged and skipped so the rest of the run continues. Exit code is non-zero if any
project failed.

**Configuration** (`config/services.php` → `.env`):

| Env var | Meaning |
|---|---|
| `ESIGNON_BASE_URL` | API root, default `https://docs.esignon.net/api/` |
| `ESIGNON_COMPANY_ID` | ESIGNON company/tenant id |
| `ESIGNON_MEMB_EMAIL` | ESIGNON account email |
| `ESIGNON_MEMB_PASSWORD` | ESIGNON account password |

Code: `app/Console/Commands/SyncEsignon.php`, `app/Services/Esignon/EsignonClient.php`
(API + parsing), `app/Services/Esignon/EsignonSync.php` (pull + match).

---

## Scheduled tasks

Defined in `routes/console.php`; production needs the usual single cron entry
(`* * * * * php artisan schedule:run`).

| Schedule | Command | Purpose |
|---|---|---|
| Hourly (`withoutOverlapping`) | `esignon:sync` | Keep ESIGNON delegations flowing onto the roster |

Inspect with `lando artisan schedule:list`.

---

## Composer scripts

```bash
composer setup        # first-time install: deps, .env, key, migrate, npm build
composer dev          # dev stack: serve + queue + logs (pail) + vite, concurrently
composer lint         # pint --parallel (format)
composer lint:check   # pint --parallel --test (check only)
composer test         # config:clear + lint:check + php artisan test
```

---

## Frequently used artisan commands

```bash
lando artisan migrate            # run migrations
lando artisan test               # test suite (sqlite in-memory, per phpunit.xml)
lando artisan view:clear         # after adding/changing Blade icons or views
lando artisan icons:cache        # production: build the Blade Icons manifest
lando artisan icons:clear        # remove the icon manifest
lando artisan schedule:list      # show scheduled tasks
```

Formatting is `vendor/bin/pint` (check-only: `vendor/bin/pint --test`) — see
`CLAUDE.md` for project conventions.
