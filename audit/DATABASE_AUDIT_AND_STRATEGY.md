# Database Audit & Strategy

Companion to [MIGRATION_AUDIT.md](MIGRATION_AUDIT.md). Sources: [SUPABASE_NOTES.md](SUPABASE_NOTES.md), [SUPABASE_SAMPLE_DATA.md](SUPABASE_SAMPLE_DATA.md), [PHP_STORAGE_DATABASE.md](PHP_STORAGE_DATABASE.md), plus code in `leenmore-app` and `leenmore-storage`.

---

## 1. Current database sources

| Source | Role | Evidence |
|---|---|---|
| **Supabase Postgres** | System of record for all app data (projects, shareholders, submissions, receipts, resources, options) and auth (`profiles` + Supabase Auth) | `leenmore-app/src/utils/supabaseClient.js`, all hooks in `leenmore-app/src/hooks/` |
| **PHP storage MySQL** (`leenmore_storage`) | Email-to-worker queue, eSignon sync staging, upload-folder registry, token cache | `leenmore-storage/bootstrap.php`, `PHP_STORAGE_DATABASE.md` |
| **Laravel DB in leenmore-hub** | Starter tables only: `users`, `passkeys`, 2FA columns, `cache`, `jobs`, `sessions` | `leenmore-hub/database/migrations/` |

Target: **one** Laravel MySQL database absorbing both legacy DBs. Supabase and the storage DB are retired after cutover.

---

## 2. Supabase table review

### `profiles`
- **Used for:** user identity + role; paired with Supabase Auth users (same uuid).
- **Columns:** `id` uuid, `email`, `first_name`, `last_name`, `role` (admin/worker), `status` (active/…), `phone_number`, `email_receiver` bool.
- **Relationships:** uuid referenced by `submission.user_id`, `receipt.user_id`, `shareholder.user` array.
- **Problems:** role/status as free text; duplicate of auth.users.
- **Laravel:** merge into `users` (Fortify). `status` becomes `deactivated_at`. **Removed as a table.**

### `project`
- **Used for:** campaign/projects shown to admin and workers.
- **Columns:** `id` int8, `title`, `status` (publish/draft), `results` json array of `{name, color, contactRequired, attachmentRequired, order}`, `shares_issued` text, `shares_target` text, `start_date`/`end_date` timestamptz, `message`, `link_manage_id` text (eSignon link).
- **Problems:** numeric shares stored as text; no soft delete (status doubles as visibility).
- **Laravel:** becomes `projects`, merged with the storage `project` table; shares fields become integers; soft deletes added.

### `shareholder`
- **Used for:** the people each project's workers contact. Largest, messiest table.
- **Columns:** identity (`name`, `registration`, `sex`, `person_type`, `code`, `date_of_birth`, `date_of_birth_code`), shares (`shares`, `shares_total` — text), contact (`contact_info`, `contact_info_2`, `address`), assignment (`user` **uuid array** = assigned workers — verified via `.contains("user", [...])` in `leenmore-app/src/hooks/useShareholder.js:227,319,389`, `useProject.js:188,269`), denormalized state (`result`, `last_note`, `contact_worker`, `prev_comment`, `prev_result`, `prev_note` — still used, e.g. `pages/Admin/components/ShareholderTable.jsx`), eSignon results (`api_recipient_contact`, `api_recipient_completion_date`), import artifacts (`row`, `no`, `database`), and a typo column `eletronic_voting`.
- **Problems:** uuid array instead of a pivot; text numerics; heavy denormalization; typo column name.
- **Laravel:** becomes `shareholders` + `shareholder_user` pivot. Denormalized columns kept (the UI depends on them); types fixed; typo fixed to `electronic_voting`.

### `submission`
- **Used for:** worker visit/contact reports per shareholder.
- **Columns:** `user_id` uuid, `user_name` (denormalized), `shareholder_id`, `project_id`, `date` timestamptz, `result`, `contact_worker`, `note`, `files` text array, `privacy_consent_file` text array, `is_deleted` bool.
- **Laravel:** `submissions`; arrays → JSON; `is_deleted` → `deleted_at`; `user_name` kept for history.

### `receipt`
- **Used for:** worker expense receipts.
- **Columns:** `date` timestamptz, `usage_history`, `where_used`, `amount` **text**, `user_id` uuid, `attachments` text array, `status` bool (sample data: `true` = active; React fetches `.eq("status", true)` and "deletes" by setting `false`), `user_name`, `note`.
- **Laravel:** `receipts`; `amount` → decimal; `status=false` → `deleted_at` (polarity re-verified at import).

### `options`
- **Used for:** app settings — `submission_deadline` (in `value`), `usage_history` list (in `multivalue` array).
- **Problems:** two value columns (`value` text + `multivalue` array).
- **Laravel:** `options` with a single JSON `value` column; `name` → unique `key`.

### `resource`
- **Used for:** per-project files and links shown to workers.
- **Columns:** `title`, `path`, `url`, `type` (file/link), `parent_id` **text**, `attached_to` (always `'project'` in sample/code), `order`.
- **Problems:** polymorphic-ish `parent_id`/`attached_to` as text, but only ever used for projects.
- **Laravel:** `resources` with a real `project_id` FK; `order` → `sort_order`.

---

## 3. PHP storage table review

### `project`
- **Used for:** registry of upload folder names (`id` int, `title` text, `created_at` date) — created on first upload (`leenmore-storage/src/Controller/DatabaseController.php`).
- **Risk:** `id` is supplied by the React app from Supabase, so it should equal Supabase `project.id` — **needs confirmation** before merge.
- **Disposition: merged** into Laravel `projects` (title only matters for on-disk folder naming).

### `email_jobs`
- **Used for:** queued email-to-worker batches. `worker_report`/`worker_report_pdf` are generated report paths; `created_by_admin_id` int — referent unclear (Supabase profiles are uuids) — **needs confirmation**.
- **Disposition: kept** as `email_jobs` (FKs renamed, paths to varchar).

### `email_recipients`
- **Used for:** per-recipient send state (`status` enum, `attempts`, `sent_at`, `error_message`). `worker_id` int — referent unclear — **needs confirmation**.
- **Disposition: kept** as `email_recipients` (`job_id` → `email_job_id`).

### `email_links` / `email_attachments`
- **Used for:** links in the email body; file attachments (`resource_id` likely → Supabase `resource.id`).
- **Disposition: kept**, FKs renamed and properly constrained.

### `esignon_shareholders`
- **Used for:** staging of completed e-signature rows downloaded from eSignon, later pushed to Supabase. Note `project_id` is **varchar(36)** while Supabase project id is int8, and `link_manage_id`/`link_id` are **int** while Supabase `project.link_manage_id` is text — **needs confirmation** of actual contents. Unique key `(project_id, link_id)`; `data_hash` char(64) dedup.
- **Disposition: kept** with typed columns and a real `project_id` FK; sync flags become transitional.

### `esignon_auth`
- **Used for:** caching the eSignon access token (single row).
- **Disposition: removed** — replaced by Laravel `Cache::remember()`.

### `supabase_projects_cache`
- **Used for:** local cache of active Supabase projects so crons know what to sync.
- **Disposition: removed** — obsolete once `projects` lives in the same database.

---

## 4. Final Laravel database direction

Full column-level detail lives in [FINAL_DATABASE_SCHEMA_PROPOSAL.md](FINAL_DATABASE_SCHEMA_PROPOSAL.md). Summary:

| Table | Purpose | Legacy mapping cols | Soft deletes | JSON cols |
|---|---|---|---|---|
| `users` | auth + roles (absorbs `profiles`) | `supabase_uuid` | no (`deactivated_at`) | no |
| `projects` | campaigns (merges both `project` tables) | `supabase_id`, `storage_id` | yes | `results` |
| `shareholders` | contact targets per project | `supabase_id` | no (hard-deleted in legacy) | no |
| `shareholder_user` | worker assignment (replaces uuid array) | — | no | no |
| `submissions` | worker reports | `supabase_id` | yes (← `is_deleted`) | `files`, `privacy_consent_files` |
| `receipts` | worker expenses | `supabase_id` | yes (← `status`) | `attachments` |
| `resources` | project files/links | `supabase_id` | no | no |
| `options` | app settings | `supabase_id` | no | `value` |
| `email_jobs` | email batches | `storage_id` | no | no |
| `email_recipients` | per-recipient send state | `storage_id` | no | no |
| `email_links` | email body links | `storage_id` | no | no |
| `email_attachments` | email attachments | `storage_id` | no | no |
| `esignon_shareholders` | eSignon staging | `storage_id` | no | no |

---

## 5. Merge strategy

- **`projects` ← Supabase `project` + storage `project`.** Supabase wins for all content. The storage row contributes nothing except confirming the on-disk folder id/title; match on id (expected identical — confirm in §10). Store both `supabase_id` and `storage_id`.
- **`users` ← Laravel `users` + Supabase `profiles`.** Match key: email (lowercased). `first_name + last_name` → `name` (single column — confirm in §10). `role`, `phone_number` → `phone`, `email_receiver` carried over; `status != 'active'` → `deactivated_at = now()`. Passwords are not importable → forced reset (already decided).
- **`shareholders`/`submissions`/`receipts`/`resources`/`options`:** straight imports. Every uuid FK (`user_id`) remapped via `supabase_uuid` → `users.id`. The `shareholder.user` array explodes into `shareholder_user` pivot rows.
- **PHP email/eSignon data:** imported as-is into the renamed tables; `job_id` → `email_job_id`; `created_by_admin_id`/`worker_id` remapped once their referent is confirmed (§10); `esignon_shareholders.project_id` remapped to `projects.id`.
- **Report paths** (`email_jobs.worker_report*`): rewritten from legacy relative paths to the `worker-reports` disk during import.

## 6. Data type strategy

| Legacy | Laravel | Conversion rule |
|---|---|---|
| Supabase uuid PKs (`profiles.id`) | `bigint` auto-increment + `supabase_uuid` char(36) kept on `users` only | remap all uuid FKs through the users mapping |
| Postgres text arrays (`files`, `attachments`, `privacy_consent_file`, `options.multivalue`) | `json` columns | direct array → JSON |
| Postgres uuid array (`shareholder.user`) | `shareholder_user` pivot rows | one row per uuid; unknown uuids logged, skipped |
| `timestamptz` | `datetime`/`date` | convert to Asia/Seoul (app timezone) at import |
| Postgres `bool` | `tinyint(1)` | direct |
| Text numerics (`shares`, `shares_total`, `shares_issued`, `shares_target`) | `unsignedBigInteger` nullable | strip commas/whitespace; non-numeric → NULL + logged to an import report |
| Text money (`receipt.amount`) | `decimal(14,2)` nullable | same sanitization (KRW values, typically integers) |
| `submission.is_deleted` bool | `deleted_at` timestamp | `true` → `updated_at`-based timestamp |
| `receipt.status` bool | `deleted_at` timestamp | `false` → deleted (sample shows `true` = active; re-verify) |
| Storage enums (`status` on email tables) | `varchar` with app-level enum (PHP backed enum) | direct |

## 7. Relationship strategy

- `User` hasMany `Submission`, `Receipt`; hasMany `EmailJob` (as creator); belongsToMany `Shareholder` (pivot `shareholder_user`).
- `Project` hasMany `Shareholder`, `Submission`, `Resource`, `EsignonShareholder`, `EmailJob`; softDeletes.
- `Shareholder` belongsTo `Project`; hasMany `Submission`; belongsToMany `User` (assigned workers).
- `Submission` belongsTo `Project`, `Shareholder`, `User` (nullable); softDeletes.
- `Receipt` belongsTo `User`; softDeletes.
- `Resource` belongsTo `Project`; hasMany `EmailAttachment` (nullable back-ref).
- `EmailJob` belongsTo `Project` (nullable), `User` creator (nullable); hasMany `EmailRecipient`, `EmailLink`, `EmailAttachment`.
- `EmailRecipient` belongsTo `EmailJob`, `User` (nullable).
- `EsignonShareholder` belongsTo `Project` (nullable).

## 8. Index and performance strategy

- **Project lookups:** `projects(status)`; `projects.supabase_id` / `storage_id` unique (also serve import upserts).
- **Shareholder search/filter:** `shareholders(project_id, name)`, `shareholders(project_id, result)`, `shareholders(date_of_birth_code)`, plain index on `name` for the global worker search.
- **Worker-assigned shareholders:** `shareholder_user` unique `(shareholder_id, user_id)` + index `(user_id)` — replaces the Postgres GIN array index.
- **Submissions:** `(project_id, created_at)`, `(shareholder_id)`, `(user_id)`, `(date)` — soft deletes filter on `deleted_at` automatically.
- **Receipts:** `(user_id, date)`, `(date)`.
- **Email queue:** `email_recipients(status, attempts)` for batch claiming, `(email_job_id, status)` for job completion checks; `email_jobs(status)`, `email_jobs(project_id)`.
- **eSignon sync:** unique `(project_id, link_id)` (preserved from legacy), index `(needs_sync)`, index `(data_hash)`.
- **Resources:** `(project_id, sort_order)`.

## 9. Import strategy

**Safest order (FK dependency order):**
1. Supabase: `profiles` → `users`
2. Supabase `project` + storage `project` (merge) → `projects`
3. Supabase `shareholder` → `shareholders`
4. `shareholder.user` arrays → `shareholder_user`
5. Supabase `submission` → `submissions`
6. Supabase `receipt` → `receipts`
7. Supabase `resource` → `resources`
8. Supabase `options` → `options`
9. Storage `esignon_shareholders` → `esignon_shareholders`
10. Storage `email_jobs` → `email_jobs`, then `email_recipients` / `email_links` / `email_attachments`
11. Files (uploads, receipts, resources, consents, worker-reports) — last, with path verification

**ID mapping:** in-memory `old → new` map per table during the run, persisted as `supabase_id` / `storage_id` / `supabase_uuid` columns.
**Idempotency:** every insert is an upsert keyed on the legacy-mapping column — re-running the import (including the final cutover re-run) updates rather than duplicates.
**Row-count verification:** after each table, compare source count vs imported count (minus logged skips); emit a per-table report.
**Duplicate prevention:** unique constraints on every legacy-mapping column + `data_hash` + `(project_id, link_id)`.
**Files/attachments:** copy preserving the legacy `{project}/{date}` substructure into the Laravel disks; strip per-disk path prefixes (`upload/`, `receipts/`, `resources/`, `privacy-consent/`…) from DB path strings; verify every path stored in `submissions.files`, `receipts.attachments`, `resources.path`, `email_attachments.file_path` resolves on its disk; report misses.

## 10. Risks and open questions before migrations

1. **Does storage `project.id` equal Supabase `project.id`?** Expected yes (React passes the Supabase id to `/upload-files`), but must be verified against real data before the merge.
2. **`email_jobs.created_by_admin_id` and `email_recipients.worker_id` are ints** — Supabase users are uuids. What do they reference? (Possibly unused or an internal admin numbering.)
3. **`shareholder.user` array contents:** sample shows uuids, but `useShareholder.js:227` passes a *user name* into `.contains("user", [user_name])`. Uuids, names, or mixed? Determines pivot import logic.
4. **`receipt.status` polarity:** sample says `true` = active; confirm no third meaning (e.g. approved).
5. **`profiles.status` value set:** only `active` seen; what are the others (deactivated? pending?) — `/deactivate-account` page suggests deactivation exists.
6. **Name fields:** single `name` column vs keeping `first_name`/`last_name` split.
7. **`shareholder.database` column meaning** (source-list label?) — affects naming (`source_database`?).
8. **Dirty numerics:** do `shares`/`amount` contain commas, units, or blanks in real data?
9. **`esignon_shareholders.project_id` varchar(36) contents:** int-as-string or uuid? And `link_manage_id` int (storage) vs text (Supabase sample `"example-project-link"`).
10. **File paths:** keep legacy relative paths verbatim in DB (recommended) or re-layout to new naming at import?
11. **`project.link_manage_id` ↔ eSignon:** confirmed as the eSignon link key by `leenmore-storage/cron/save_esignon_shareholders.php` flow — but confirm the int vs text discrepancy above.

## 11. Recommended migration phases (database work)

1. **Audit** — done (this document + `MIGRATION_AUDIT.md`).
2. **Final schema design** — [FINAL_DATABASE_SCHEMA_PROPOSAL.md](FINAL_DATABASE_SCHEMA_PROPOSAL.md); answer §10 questions; freeze.
3. **Migrations** — Laravel migration files matching the frozen proposal (no data).
4. **Import commands** — `legacy:import` (read-only against Supabase pgsql + storage MySQL dump), per-table, idempotent, with `--only=` table filter and dry-run mode.
5. **File import** — `legacy:import-files` with path-resolution report.
6. **Verification** — row counts, FK orphan scan, attachment resolution, spot-check UI against the live React app.
7. **Cutover** — freeze legacy writes, final re-run of imports, switch.
