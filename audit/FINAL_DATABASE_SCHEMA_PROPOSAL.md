# Final Database Schema Proposal

Concrete column-level proposal for the consolidated Laravel database.
Source of truth: [DATABASE_AUDIT_AND_STRATEGY.md](DATABASE_AUDIT_AND_STRATEGY.md). Schema sources: [SUPABASE_NOTES.md](SUPABASE_NOTES.md), [PHP_STORAGE_DATABASE.md](PHP_STORAGE_DATABASE.md), [SUPABASE_SAMPLE_DATA.md](SUPABASE_SAMPLE_DATA.md).

Conventions used throughout:
- All tables: `id` = `bigIncrements` primary key, plus `created_at`/`updated_at` timestamps, unless noted.
- "Type" = Laravel schema builder method (MySQL type in parentheses where useful).
- Legacy mapping columns (`supabase_id`, `supabase_uuid`, `storage_id`) are nullable + unique — they make the import idempotent and are kept after cutover for traceability.
- Fields marked **Needs confirmation** must be answered (see §10 of the strategy doc) before migrations are written.

---

## 1. `users`

**Purpose:** authentication + roles. Existing Fortify table extended to absorb Supabase `profiles`.

| Column | Type | Null | Default | Source / notes |
|---|---|---|---|---|
| id | bigIncrements | no | — | new |
| name | string(255) | no | — | `profiles.first_name + ' ' + last_name` — single column vs split: **Needs confirmation** |
| email | string(255) | no | — | `profiles.email` (lowercased; match key vs existing users) |
| email_verified_at | timestamp | yes | null | existing; imports set to import time (verification disabled) |
| password | string(255) | no | — | random at import; forced reset (decided) |
| role | string(20) | no | `'worker'` | `profiles.role` (`admin` / `worker`) — app-level PHP backed enum |
| phone | string(50) | yes | null | `profiles.phone_number` |
| email_receiver | boolean | no | `false` | `profiles.email_receiver` |
| deactivated_at | timestamp | yes | null | `profiles.status != 'active'` → set; value set of `status`: **Needs confirmation** |
| supabase_uuid | char(36) | yes | null | `profiles.id` — legacy mapping |
| two_factor_* / remember_token | — | — | — | existing starter columns, unchanged |

- **PK:** id. **FKs:** none.
- **Indexes:** existing. **Unique:** email (existing), supabase_uuid.
- **Soft deletes:** no (`deactivated_at` covers the legacy "deactivate account" flow). **JSON:** no.
- **Legacy mapping:** `supabase_uuid`.

---

## 2. `projects`

**Purpose:** campaigns. Merges Supabase `project` (content) + PHP storage `project` (upload-folder registry).

| Column | Type | Null | Default | Source / notes |
|---|---|---|---|---|
| id | bigIncrements | no | — | new |
| title | string(255) | no | — | Supabase `project.title` (storage `project.title` should match — drives on-disk folder names) |
| status | string(20) | no | `'draft'` | `project.status` (`publish` / `draft`) |
| results | json | yes | null | `project.results` — array of `{name, color, contactRequired, attachmentRequired, order}` |
| message | text | yes | null | `project.message` (shown to workers) |
| link_manage_id | string(255) | yes | null | `project.link_manage_id` (eSignon link key; text in Supabase, int in storage — **Needs confirmation**) |
| shares_issued | unsignedBigInteger | yes | null | `project.shares_issued` text → int (sanitized) |
| shares_target | unsignedBigInteger | yes | null | `project.shares_target` text → int (sanitized) |
| start_date | date | yes | null | `project.start_date` timestamptz → KST date |
| end_date | date | yes | null | `project.end_date` timestamptz → KST date |
| supabase_id | unsignedBigInteger | yes | null | Supabase `project.id` |
| storage_id | unsignedBigInteger | yes | null | storage `project.id` (expected = supabase_id — **Needs confirmation**) |
| deleted_at | timestamp | yes | null | soft deletes (new — legacy had none) |

- **PK:** id. **FKs:** none.
- **Indexes:** status. **Unique:** supabase_id, storage_id.
- **Soft deletes:** yes. **JSON:** `results`.
- **Legacy mapping:** `supabase_id`, `storage_id`.

---

## 3. `shareholders`

**Purpose:** the people workers contact per project. All real Supabase columns kept, typed properly.

| Column | Type | Null | Default | Source / notes |
|---|---|---|---|---|
| id | bigIncrements | no | — | new |
| project_id | foreignId → projects | no | — | `shareholder.project_id` (remapped) |
| name | string(255) | no | — | `shareholder.name` |
| registration | string(100) | yes | null | `shareholder.registration` |
| sex | string(10) | yes | null | `shareholder.sex` |
| person_type | string(50) | yes | null | `shareholder.person_type` (`individual` / `corporation`) |
| code | string(100) | yes | null | `shareholder.code` |
| shares | unsignedBigInteger | yes | null | `shareholder.shares` text → int (sanitized; dirty values: **Needs confirmation**) |
| shares_total | unsignedBigInteger | yes | null | `shareholder.shares_total` text → int |
| contact_info | string(255) | yes | null | `shareholder.contact_info` |
| contact_info_2 | string(255) | yes | null | `shareholder.contact_info_2` |
| contact_worker | string(255) | yes | null | `shareholder.contact_worker` — denormalized worker name, kept (UI uses it) |
| address | string(500) | yes | null | `shareholder.address` |
| source_database | string(255) | yes | null | `shareholder.database` — renamed (`database` is ambiguous); meaning: **Needs confirmation** |
| date_of_birth | date | yes | null | `shareholder.date_of_birth` |
| date_of_birth_code | string(10) | yes | null | `shareholder.date_of_birth_code` (6-digit KR code; eSignon match key) |
| result | string(100) | yes | null | `shareholder.result` — denormalized latest result, kept (filters/colors) |
| last_note | text | yes | null | `shareholder.last_note` |
| row_no | unsignedBigInteger | yes | null | `shareholder.row` — renamed (`ROW` is reserved in MySQL 8) |
| no | unsignedBigInteger | yes | null | `shareholder.no` (list number) |
| electronic_voting | string(20) | yes | null | `shareholder.eletronic_voting` — typo fixed |
| prev_comment | text | yes | null | `shareholder.prev_comment` |
| prev_result | string(100) | yes | null | `shareholder.prev_result` |
| prev_note | text | yes | null | `shareholder.prev_note` |
| api_recipient_contact | string(255) | yes | null | `shareholder.api_recipient_contact` (written by eSignon sync) |
| api_recipient_completion_date | date | yes | null | `shareholder.api_recipient_completion_date` (written by eSignon sync) |
| supabase_id | unsignedBigInteger | yes | null | Supabase `shareholder.id` |

- **PK:** id. **FKs:** project_id → projects.id (cascade on delete).
- **Indexes:** (project_id, name), (project_id, result), date_of_birth_code, name.
- **Unique:** supabase_id.
- **Soft deletes:** no (legacy hard-deletes shareholders). **JSON:** no.
- **Legacy mapping:** `supabase_id`. *(The legacy `user` uuid array moves to `shareholder_user`.)*

---

## 4. `shareholder_user`

**Purpose:** worker assignment pivot — replaces the `shareholder.user` uuid array (verified usage: `leenmore-app/src/hooks/useShareholder.js:227,319,389`).

| Column | Type | Null | Default | Source / notes |
|---|---|---|---|---|
| id | bigIncrements | no | — | new |
| shareholder_id | foreignId → shareholders | no | — | array owner row |
| user_id | foreignId → users | no | — | each uuid in `shareholder.user`, remapped via `users.supabase_uuid`; array may hold names instead of uuids at some call sites — **Needs confirmation** |
| created_at / updated_at | timestamps | yes | null | new |

- **PK:** id. **FKs:** shareholder_id → shareholders (cascade), user_id → users (cascade).
- **Indexes:** user_id. **Unique:** (shareholder_id, user_id).
- **Soft deletes:** no. **JSON:** no. **Legacy mapping:** none (derived data).

---

## 5. `submissions`

**Purpose:** worker contact/visit reports per shareholder.

| Column | Type | Null | Default | Source / notes |
|---|---|---|---|---|
| id | bigIncrements | no | — | new |
| project_id | foreignId → projects | no | — | `submission.project_id` (remapped) |
| shareholder_id | foreignId → shareholders | no | — | `submission.shareholder_id` (remapped) |
| user_id | foreignId → users | yes | null | `submission.user_id` uuid → remapped; null if user missing |
| user_name | string(255) | yes | null | `submission.user_name` — denormalized, kept for history |
| date | datetime | yes | null | `submission.date` timestamptz → KST |
| result | string(100) | yes | null | `submission.result` (matches a `projects.results[].name`) |
| contact_worker | string(255) | yes | null | `submission.contact_worker` |
| note | text | yes | null | `submission.note` |
| files | json | yes | null | `submission.files` text array (disk-relative paths) |
| privacy_consent_files | json | yes | null | `submission.privacy_consent_file` text array |
| supabase_id | unsignedBigInteger | yes | null | Supabase `submission.id` |
| deleted_at | timestamp | yes | null | ← `submission.is_deleted` |

- **PK:** id. **FKs:** project_id (cascade), shareholder_id (cascade), user_id (null on delete).
- **Indexes:** (project_id, created_at), shareholder_id, user_id, date.
- **Unique:** supabase_id.
- **Soft deletes:** yes. **JSON:** `files`, `privacy_consent_files`.
- **Legacy mapping:** `supabase_id`.

---

## 6. `receipts`

**Purpose:** worker expense receipts.

| Column | Type | Null | Default | Source / notes |
|---|---|---|---|---|
| id | bigIncrements | no | — | new |
| user_id | foreignId → users | no | — | `receipt.user_id` uuid → remapped |
| user_name | string(255) | yes | null | `receipt.user_name` — denormalized, kept |
| date | date | no | — | `receipt.date` timestamptz → KST date |
| usage_history | string(100) | yes | null | `receipt.usage_history` (value from `options.usage_history` list) |
| where_used | string(255) | yes | null | `receipt.where_used` |
| amount | decimal(14,2) | yes | null | `receipt.amount` text → decimal (KRW; sanitized) |
| attachments | json | yes | null | `receipt.attachments` text array |
| note | text | yes | null | `receipt.note` |
| supabase_id | unsignedBigInteger | yes | null | Supabase `receipt.id` |
| deleted_at | timestamp | yes | null | ← `receipt.status = false` (polarity: **Needs confirmation** — sample shows `true` = active) |

- **PK:** id. **FKs:** user_id → users (cascade).
- **Indexes:** (user_id, date), date.
- **Unique:** supabase_id.
- **Soft deletes:** yes. **JSON:** `attachments`.
- **Legacy mapping:** `supabase_id`.

---

## 7. `resources`

**Purpose:** per-project files and links for workers. Replaces text `parent_id`/`attached_to` with a real FK (`attached_to` is always `'project'` in code and sample data).

| Column | Type | Null | Default | Source / notes |
|---|---|---|---|---|
| id | bigIncrements | no | — | new |
| project_id | foreignId → projects | no | — | `resource.parent_id` (text) where `attached_to = 'project'`, remapped |
| title | string(255) | no | — | `resource.title` |
| type | string(20) | no | `'file'` | `resource.type` (`file` / `link`) |
| path | string(500) | yes | null | `resource.path` (file resources; disk-relative) |
| url | string(1000) | yes | null | `resource.url` (link resources) |
| sort_order | unsignedInteger | no | `0` | `resource.order` — renamed (reserved word) |
| supabase_id | unsignedBigInteger | yes | null | Supabase `resource.id` |

- **PK:** id. **FKs:** project_id → projects (cascade).
- **Indexes:** (project_id, sort_order). **Unique:** supabase_id.
- **Soft deletes:** no (legacy hard-deletes). **JSON:** no.
- **Legacy mapping:** `supabase_id`.

---

## 8. `options`

**Purpose:** app settings (`submission_deadline`, `usage_history` list). Merges legacy `value` text + `multivalue` array into one JSON column.

| Column | Type | Null | Default | Source / notes |
|---|---|---|---|---|
| id | bigIncrements | no | — | new |
| key | string(100) | no | — | `options.name` |
| value | json | yes | null | `options.value` (scalar → JSON string) or `options.multivalue` (array → JSON array) |
| supabase_id | unsignedBigInteger | yes | null | Supabase `options.id` |

- **PK:** id. **FKs:** none.
- **Indexes:** —. **Unique:** key, supabase_id.
- **Soft deletes:** no. **JSON:** `value`.
- **Legacy mapping:** `supabase_id`.

---

## 9. `email_jobs`

**Purpose:** queued email-to-worker batches (from PHP storage).

| Column | Type | Null | Default | Source / notes |
|---|---|---|---|---|
| id | bigIncrements | no | — | new |
| project_id | foreignId → projects | yes | null | `email_jobs.project_id` (remapped) |
| created_by_id | foreignId → users | yes | null | `email_jobs.created_by_admin_id` — int referent: **Needs confirmation** |
| subject | string(255) | no | — | `email_jobs.subject` |
| body | text | yes | null | `email_jobs.body` |
| worker_report_path | string(500) | yes | null | `email_jobs.worker_report` (text → varchar; rewritten to worker-reports disk path) |
| worker_report_pdf_path | string(500) | yes | null | `email_jobs.worker_report_pdf` |
| status | string(20) | no | `'pending'` | enum `pending` / `processing` / `completed` / `failed` (app-level PHP enum) |
| storage_id | unsignedBigInteger | yes | null | storage `email_jobs.id` |

- **PK:** id. **FKs:** project_id (null on delete), created_by_id → users (null on delete).
- **Indexes:** status, project_id. **Unique:** storage_id.
- **Soft deletes:** no. **JSON:** no.
- **Legacy mapping:** `storage_id`.

---

## 10. `email_recipients`

**Purpose:** per-recipient send state within a job.

| Column | Type | Null | Default | Source / notes |
|---|---|---|---|---|
| id | bigIncrements | no | — | new |
| email_job_id | foreignId → email_jobs | no | — | `email_recipients.job_id` |
| user_id | foreignId → users | yes | null | `email_recipients.worker_id` — int referent: **Needs confirmation** |
| email | string(255) | no | — | `email_recipients.email` |
| status | string(20) | no | `'pending'` | enum `pending` / `processing` / `sent` / `failed` |
| attempts | unsignedTinyInteger | no | `0` | `email_recipients.attempts` |
| last_attempt_at | datetime | yes | null | `email_recipients.last_attempt_at` |
| sent_at | datetime | yes | null | `email_recipients.sent_at` |
| error_message | text | yes | null | `email_recipients.error_message` |
| storage_id | unsignedBigInteger | yes | null | storage `email_recipients.id` |

- **PK:** id. **FKs:** email_job_id (cascade), user_id (null on delete).
- **Indexes:** (email_job_id, status), (status, attempts) — batch claiming. **Unique:** storage_id.
- **Soft deletes:** no. **JSON:** no.
- **Legacy mapping:** `storage_id`.

---

## 11. `email_links`

**Purpose:** links rendered in the email body.

| Column | Type | Null | Default | Source / notes |
|---|---|---|---|---|
| id | bigIncrements | no | — | new |
| email_job_id | foreignId → email_jobs | no | — | `email_links.job_id` |
| title | string(255) | no | — | `email_links.title` |
| url | string(1000) | no | — | `email_links.url` |
| sort_order | unsignedInteger | no | `0` | `email_links.sort_order` |
| storage_id | unsignedBigInteger | yes | null | storage `email_links.id` |

- **PK:** id. **FKs:** email_job_id (cascade).
- **Indexes:** email_job_id. **Unique:** storage_id.
- **Soft deletes:** no. **JSON:** no. **Legacy mapping:** `storage_id`.

---

## 12. `email_attachments`

**Purpose:** file attachments per email job.

| Column | Type | Null | Default | Source / notes |
|---|---|---|---|---|
| id | bigIncrements | no | — | new |
| email_job_id | foreignId → email_jobs | no | — | `email_attachments.job_id` |
| resource_id | foreignId → resources | yes | null | `email_attachments.resource_id` (likely Supabase resource id — remap; verify) |
| filename | string(255) | no | — | `email_attachments.filename` |
| file_path | string(500) | no | — | `email_attachments.file_path` (rewritten to disk-relative) |
| file_size | unsignedBigInteger | yes | null | `email_attachments.file_size` |
| storage_id | unsignedBigInteger | yes | null | storage `email_attachments.id` |

- **PK:** id. **FKs:** email_job_id (cascade), resource_id (null on delete).
- **Indexes:** email_job_id. **Unique:** storage_id.
- **Soft deletes:** no. **JSON:** no. **Legacy mapping:** `storage_id`.

---

## 13. `esignon_shareholders`

**Purpose:** staging rows downloaded from the eSignon API, matched against `shareholders` by name + date_of_birth_code. Sync flags are transitional (only needed while Supabase is still live) and dropped at cutover.

| Column | Type | Null | Default | Source / notes |
|---|---|---|---|---|
| id | bigIncrements | no | — | new |
| project_id | foreignId → projects | yes | null | legacy varchar(36) — contents (int-as-string vs uuid): **Needs confirmation**; remapped |
| link_manage_id | string(255) | yes | null | legacy int ↔ Supabase text — store as string; **Needs confirmation** |
| link_id | string(255) | yes | null | legacy int → string (part of legacy unique key) |
| identifier | text | yes | null | legacy `identifier` (DOB code field) |
| name | string(255) | yes | null | legacy `name` |
| contact | string(255) | yes | null | legacy `contact` |
| completed_date | datetime | yes | null | legacy `completed_date` |
| data_hash | char(64) | no | — | legacy dedup hash (sha-256) — algorithm must be preserved exactly |
| needs_sync | boolean | no | `false` | ← `needs_supabase_sync` (transitional; legacy default was 1) |
| synced_at | datetime | yes | null | ← `supabase_synced_at` (transitional) |
| storage_id | unsignedBigInteger | yes | null | storage `esignon_shareholders.id` |

- **PK:** id. **FKs:** project_id → projects (null on delete).
- **Indexes:** link_manage_id, needs_sync, data_hash.
- **Unique:** (project_id, link_id) — preserved from legacy; storage_id.
- **Soft deletes:** no. **JSON:** no.
- **Legacy mapping:** `storage_id`.

---

## Tables Not Migrated

| Legacy table | Why removed |
|---|---|
| **`profiles`** (Supabase) | Fully absorbed into `users` — role, phone, email_receiver, deactivation, and the uuid live there now. Keeping a separate profile table would split identity across two tables for no benefit. |
| **PHP storage `project`** | Only three columns (`id`, `title`, `created_at`) acting as an upload-folder registry; merged into `projects` (`storage_id` keeps the mapping). A second project table would duplicate the same entity. |
| **`esignon_auth`** | A one-row token cache for the eSignon API. Laravel's cache (`Cache::remember('esignon.token', …)`) replaces it — no schema needed. |
| **`supabase_projects_cache`** | Existed only so PHP crons knew which Supabase projects to sync. Once `projects` lives in the same database, the cache is meaningless — scheduled jobs query `projects` directly. |
