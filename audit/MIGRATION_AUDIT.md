# Leenmore Migration Audit

**Goal:** consolidate `leenmore-storage` (plain-PHP file/email/report server) and `leenmore-app` (React + Supabase portal) into `leenmore-hub` (Laravel 13 + Livewire 4), preserving current look and behavior. React is retired; Supabase is retired after full data migration.

**Confirmed decisions**

- Full data migration: Supabase Postgres tables are imported into Laravel's MySQL database; Supabase is retired at cutover.
- Only these three projects consume Supabase — no mobile app or other client dependency.
- Auth moves to Laravel Fortify (already scaffolded). Supabase users are imported with admin/worker roles; users reset passwords on first login.

---

## 1. What each project does

### leenmore-hub (target — the only project we modify)
Fresh Laravel 13 starter kit: Livewire 4 + Flux 2 UI, Fortify auth with 2FA + passkeys, Tailwind 4 via Vite, MySQL 8 through Lando (`.lando.yml`), SQLite default in `.env.example`. No domain code yet — one `User` model and the settings/auth scaffold.

### leenmore-storage (PHP file/email/report server)
Frameworkless PHP 8.3 app. All HTTP requests route through `index.php` (Apache rewrite). Provides file uploads, zip downloads, an email queue (PHPMailer over Hostinger SMTP), XLSX/PDF worker-report generation, and two-way sync between the eSignon e-signature service and Supabase. Local MySQL DB `leenmore_storage`. Four cron scripts under `cron/`.

### leenmore-app (React frontend)
React 18 (CRA), MUI + Tailwind + styled-components, Jotai + React Query. Talks to Supabase directly (data + auth + realtime) and to leenmore-storage over axios for files/emails/reports. Two role-gated areas: Admin (`/dashboard/...`) and Worker (`/app/...`). ~169 JSX files.

---

## 2. Current Laravel setup in leenmore-hub

- `composer.json` — laravel/framework ^13.7, livewire/livewire ^4.1, livewire/flux ^2.13, laravel/fortify ^1.37, laravel/chisel.
- Routes: `routes/web.php` (`/`, `/dashboard`), `routes/settings.php` (profile/appearance/security via `Route::livewire()`).
- Livewire components: `app/Livewire/Settings/*` (class components, `#[Title]` attributes); views under `resources/views/livewire/...`.
- Layouts: `resources/views/layouts/app.blade.php` (wraps `x-layouts::app.sidebar`), `layouts/auth.blade.php`.
- Migrations: users (+2FA columns), passkeys, cache, jobs.
- Drivers: queue/cache/session = `database`; broadcast = `log`; mail = `log`; filesystem = `local`.
- Auth: Fortify with registration, email verification, 2FA, passkeys, password confirmation (feature-toggled via Chisel markers, e.g. `app/Livewire/Settings/Security.php`).

---

## 3. PHP/server functionality in leenmore-storage

### HTTP endpoints (all in `leenmore-storage/index.php`; sensitive ones check `AUTH_TOKEN` env)

| Endpoint | Handler | Purpose |
|---|---|---|
| `POST /upload-files` | `src/Controller/UploadFIlesController.php` | Upload submission files to `upload/{project}/{date}/` |
| `POST /receipt-upload` | `src/Controller/UploadReceiptFilesController.php` | Upload receipts to `receipts/{user}/{date}/` |
| `POST /resource-upload` | `src/Controller/UploadResourceFilesController.php` | Upload resources to `resources/{project}/` |
| `POST /privacy-consent-file-upload` | `src/Controller/UploadPrivacyConsentFilesController.php` | Upload to `개인 정보 활용 동의서/{project}/` (Korean dir name) |
| `POST /request-time` | `src/Controller/RequestProjectTimesController.php` | List date folders for a project |
| `POST /download-zip` | `src/Controller/DownloadFolderController.php` + `ZipArchiver.php` | Zip a project date folder |
| `POST /download-zip-files` | `src/Controller/DownloadFilesController.php` | Zip arbitrary files by URL |
| `POST /insert-emails` | `app/Services/EmailSender.php::insertEmailJob()` | Queue an email batch (jobs/recipients/links/attachments tables) |
| `POST /generate-worker-report` | `app/Services/ReportGenerator.php` | XLSX + PDF report from `templates/email-to-worker.xlsx` |
| `POST /generate-multi-worker-report` | `app/Services/ReportGenerator.php` | Multi-sheet XLSX (one sheet per worker) |
| `POST /update-supabase-projects-cache` | `app/Services/Supabase/SupabaseClient.php` | Cache active Supabase projects locally |
| `POST /get-not-found-shareholders` | inline SQL in `index.php` | eSignon shareholders flagged `needs_supabase_sync` |

Legacy standalone scripts: `uploader.php`, `download.php`, `visualize-emails-sent.php` (email-queue HTML dashboard).

### Cron jobs (`leenmore-storage/cron/`)

| Script | Cadence | Behavior |
|---|---|---|
| `send_emails.php` | ~5–10 min | `EmailQueueProcessor::processPendingBatch(10, maxAttempts: 3)` via PHPMailer SMTP |
| `save_esignon_shareholders.php` | hourly | Downloads completed e-signature data per project `link_manage_id` from eSignon (`app/Services/EsignonClient/{Client,LinkService}.php`), parses Korean CSV headers, upserts `esignon_shareholders` with dedup hash |
| `upsert_shareholder_data_to_supabase.php` | ~30 min | Pushes contact/completion date to Supabase `shareholder` rows (`app/Services/Sync/ShareholderSupabaseSync.php`) |
| `save_supabase_projects_cache.php` | hourly | Refreshes `supabase_projects_cache` table |

### Local MySQL tables
`project`, `esignon_shareholders`, `esignon_auth` (token cache), `supabase_projects_cache`, `email_jobs`, `email_recipients`, `email_links`, `email_attachments`.

### Known issues to fix during migration (not before)
- SQL injection: unparameterized `$project_id` in `src/Controller/DatabaseController.php` (`getProjectFolder()`).
- `uploader.php` echoes DB credentials in its response.
- No MIME/type validation on uploads; wildcard CORS; single shared `AUTH_TOKEN`.

---

## 4. React pages/components/features in leenmore-app

Router: `leenmore-app/src/utils/Router.jsx` (React Router 6; `ProtectedRoute.jsx` / `Admin/ProtectedRouteAdmin/index.jsx` role guards). State: Jotai atoms (`src/helpers/atom.js`) + React Query hooks (`src/hooks/`). Supabase 1000-row batching helper: `src/utils/supabaseBatchFetch.js`.

**Admin features** (`src/pages/Admin/`)
- Dashboard: project cards sorted by deadline (`Dashboard/Index.jsx`).
- Projects: list/create/edit, results editor, shareholder CSV/XLSX import (`AddNewProject/`, template `public/worker_shareholder_template.xlsx`), per-project view with realtime results widget (`SingleProject/components/RealTimeResults.jsx`), `link_manage_id` editing, project message editing, folder zip download.
- Submissions: list/view/create/edit, soft delete via `is_deleted`, display of eSignon-signed submissions (`SingleSubmission/`).
- Users: CRUD with admin/worker roles (`Users/`, `AddNewUser/` — uses Supabase service-role key via `src/utils/adminAuthClient.js`).
- Receipts: review, bulk delete, submission-deadline lock, usage-history option editor, total amounts, Excel export (`Receipts/`, ExcelJS).
- Resources: per-project files + links with drag-drop ordering (`ProjectResources/`, react-beautiful-dnd).
- Activity data: per-project worker activity tables + Excel export (`ActivityData/`, `SingleActivityData/`).
- Email-to-worker: compose → `/insert-emails` + `/generate-worker-report` (`EmailToWorker/`).
- Debug page (`Debug/index.jsx`).

**Worker features** (`src/pages/Worker/`)
- Dashboard of projects (`DashboardApp/`).
- Project shareholder list + filters (`SingleProjectApp/`).
- Shareholder submission form: date, result (from `project.results` JSON), note, contact phone, file attachments, privacy-consent file (`SingleShareholderApp/SubmissionForm/`).
- Thank-you page (`Thankyou/`).
- Receipt submit: KST dates, usage-history dropdown from `options`, amount, attachments, realtime deadline warning (`ReceiptSubmit/components/DeadlineWarning.jsx`).
- Receipt archive + edit (`ReceiptArchive/`, `ReceiptSingle/`).
- Resources archive (`ProjectResourceArchiveApp/`, `SingleResourceApp/`).
- Shareholder search (`SearchShareholdersApp/`).
- Mobile bottom navigation (`components/AppMenu.jsx`, `DrawerMenuBox/`).

---

## 5. Routes / pages / user flows

URLs are preserved one-for-one in Laravel.

**Login flow:** `/` login (`src/pages/Login/`) → redirect to `/dashboard` (admin) or `/app` (worker). Replaced by Fortify login + role-based redirect.

**Admin routes** (`/dashboard` …): `''`, `project`, `project/add-new`, `project/{id}`, `project/{id}/add-more-shareholders`, `submission`, `submission/{type}/{id}`, `activity-report`, `activity-report/new/{project_id}`, `activity-report/new/{project_id}/{shareholder_id}`, `user`, `user/add-new`, `user/{id}`, `receipt`, `receipt/{receipt_id}`, `receipt/user/{user_id}`, `resources`, `resources/{project_id}`, `activity-data`, `activity-data/{project_id}`, `email-to-worker`, `email-to-worker/{project_id}`, `debug`.

**Worker routes** (`/app` …): `''`, `project/{id}`, `project/{id}/thankyou`, `project/{project_id}/shareholder/{id}`, `receipt-submit`, `my-receipts`, `my-receipts/{receipt_id}`, `resources`, `resources/{project_id}`, `search-shareholders`.

Utility: `/deactivate-account`, 404.

---

## 6. API calls and server endpoints

Two channels from React:

1. **Supabase JS client** (`src/utils/supabaseClient.js`; admin ops via `src/utils/adminAuthClient.js` with service-role key) — all CRUD on the tables in §7, auth, and realtime.
2. **Axios to leenmore-storage** (`REACT_APP_STORAGE_PATH` + `REACT_APP_STORAGE_AUTH_KEY`):

| Endpoint | React hook |
|---|---|
| `/upload-files` | `src/hooks/useFileUpload.js` |
| `/receipt-upload` | `useReceiptUpload` |
| `/resource-upload` | `useResourceUpload` |
| `/privacy-consent-file-upload` | `usePrivacyConsentUpload` |
| `/download-zip` | `useDownloadZipFolder` |
| `/download-zip-files` | `useDownloadZipFiles` |
| `/request-time` | `useSelectDownloadFolder` |
| `/insert-emails` | `useEmailSender` |

After migration, all of these become internal Laravel service calls — no public token-authenticated API remains (verify nothing else calls leenmore-storage before retiring it; check `leenmore-storage/logs/`).

---

## 7. Supabase usage

- **Tables:** `project` (status publish/draft, `results` JSON, `link_manage_id`, `message`, dates), `shareholder` (registration, no, shares, name, date_of_birth_code, api_recipient_contact, api_recipient_completion_date, …), `submission` (result, note, date, contact_worker, `is_deleted` soft delete), `receipt` (date, usage_history, where_used, amount, attachments, `status` bool soft delete, note), `profiles` (first_name, role, phone, email_receiver), `resource` (title, type file|link, url, parent_id, attached_to, order), `options` (`submission_deadline`, `usage_history` multivalue).
- **Auth:** email/password `signInWithPassword`; admin creates users with the service-role client; sessions persisted in localStorage.
- **Realtime:** `postgres_changes` channels in `DeadlineWarning.jsx` (options table) and `RealTimeResults.jsx` (live share counting).
- **Storage buckets:** none — files go to leenmore-storage instead.
- **RPC/functions:** none found in code.
- **RLS:** implied by anon-key usage from the browser; the service key is used server-side in `leenmore-storage/app/Services/Supabase/SupabaseClient.php` (REST calls to `shareholder` and `project`).

---

## 8. Assets, styles, and UI patterns to preserve

**Design tokens** (`leenmore-app/src/common/variables.js`, `Theme.jsx`, `GlobalStyles.jsx`):
- Primary brand red `#5a0713`; gradient `linear-gradient(145deg, #870000 0%, #7e0420 49%, #5a0713 100%)`.
- Palette: lightShades `#8D271A`, lightAccent `#952424`, darkAccent `#2D333A`, darkShades `#0a0b0d`, text `#2c323f`, link `#4D1254`, warning `#ff6900`, success `#22ab00`, border `#e5e5e5`, superLight `#f3f5f9`.
- Font: **Jost**, 16px base; 768px responsive breakpoint.

**Assets to carry over:**
- `leenmore-app/src/assets/images/logo.png`, `done.jpg`; `public/logo192.png`, `logo512.png`, `favicon.ico`.
- `leenmore-app/public/worker_shareholder_template.xlsx` (shareholder import template).
- `leenmore-storage/templates/email-to-worker.xlsx` (report template).
- `leenmore-storage/resources/fonts/nanum-gothic/*.ttf` (Korean PDF fonts).

**UX patterns:** toasts (→ Flux toast), MUI dialogs/tables (→ Flux modal/table), result color chips (`src/pages/components/resultColorOptions.js`, `OChip.jsx`), skeleton loaders, mobile bottom nav for workers, drag-drop resource ordering, search/filter bars, Excel/CSV/zip export buttons.

---

## 9. Features → Livewire components

Class components following the existing `app/Livewire/Settings/*` convention; views under `resources/views/livewire/{admin,worker}/`.

**Admin — `App\Livewire\Admin\…`** (layout `layouts/admin.blade.php`)

| Feature | Component(s) |
|---|---|
| Dashboard | `Admin\Dashboard` |
| Projects | `Admin\Projects\{Index,Show,Create,AddShareholders}` |
| Submissions | `Admin\Submissions\{Index,Show,Create,SelectShareholder}` |
| Activity report | `Admin\ActivityReport\Index` |
| Users | `Admin\Users\{Index,Show,Create}` |
| Receipts | `Admin\Receipts\{Index,Show,ByUser}` |
| Resources | `Admin\Resources\{Index,Manage}` |
| Activity data | `Admin\ActivityData\{Index,Show}` |
| Email to worker | `Admin\EmailToWorker\{Index,Compose}` |
| Realtime results | `Admin\Widgets\RealtimeResults` (embedded, `wire:poll`) |

**Worker — `App\Livewire\Worker\…`** (layout `layouts/worker.blade.php`)

| Feature | Component(s) |
|---|---|
| Dashboard | `Worker\Dashboard` |
| Project view | `Worker\Projects\Show` |
| Submission form | `Worker\Submissions\Create` |
| Receipts | `Worker\Receipts\{Index,Show,Create}` + `Worker\Receipts\DeadlineWarning` (`wire:poll.30s`) |
| Resources | `Worker\Resources\{Index,Show}` |
| Shareholder search | `Worker\Shareholders\Search` |

**Realtime replacement:** `wire:poll` (broadcast driver is `log`; both realtime uses are low-stakes). Swap to Reverb/Echo later only if polling load matters.
**Drag-drop:** livewire-sortable Alpine plugin. **File uploads:** Livewire `WithFileUploads`.

---

## 10. Features → controllers / services / jobs

**Services (`app/Services/`)**
- `Reports/WorkerReportGenerator.php` — mechanical port of `leenmore-storage/app/Services/ReportGenerator.php` (1673 lines of XLSX XML surgery + LibreOffice/mPDF PDF). Do **not** refactor internals; golden-file test against legacy output.
- `Reports/CustomFontMpdfWriter.php` — Korean font fallback.
- `Email/EmailQueueService.php` — replaces `/insert-emails` (enqueue into email tables).
- `Esignon/{Client,LinkService,ShareholderSyncService}.php` — eSignon API + Korean CSV parsing + dedup hash (must match legacy hash exactly). Token cache moves from `esignon_auth` table to `Cache`.
- `Files/{UploadService,ZipService}.php` — replaces upload controllers + `ZipArchiver.php`.
- `app/Support/SanitizeTitle.php` — must byte-match legacy slugification (`helpers/sanitize-title.php` and upload controllers) or migrated file paths break.

**Jobs (database queue, already configured)**
- `SendEmailBatch` — batch of 10, `$tries = 3`, `WithoutOverlapping`; replaces `cron/send_emails.php`. PHPMailer → `Mail` facade.
- `SyncEsignonShareholders` — per-project download + upsert.
- `GenerateWorkerReport` — LibreOffice is slow; never run in a web request.

**Scheduled commands (`routes/console.php`)**
- `emails:process` everyMinute; `esignon:sync` everyFifteenMinutes; transitional `supabase:push-shareholders` hourly (deleted at cutover). `supabase_projects_cache` becomes obsolete — `projects` is the source of truth.

**Controllers (only where real HTTP remains)**
- `DownloadController` — streamed zips (replaces `/download-zip`, `/download-zip-files`).
- `AttachmentController` — auth-protected file serving (files leave the public webroot).
- `ExportController` — streamed XLSX exports (receipts, activity data) + report downloads.

**Filesystem disks (`config/filesystems.php`)**
`uploads`, `receipts`, `resources`, `consents` (ASCII root replacing the Korean directory name; Korean *file* names preserved), `worker-reports` — all under `storage/app/`, keeping the legacy `{project}/{date}` substructure so date listing and zip downloads keep working.

---

## 11. Suggested Laravel folder structure

```
app/
├── Enums/UserRole.php
├── Http/
│   ├── Controllers/ (DownloadController, AttachmentController, ExportController — later phases)
│   └── Middleware/EnsureUserIsAdmin.php
├── Jobs/ (SendEmailBatch, SyncEsignonShareholders, GenerateWorkerReport — phase 6/7)
├── Livewire/
│   ├── Admin/ (Dashboard, Projects/, Submissions/, ActivityReport/, Users/,
│   │           Receipts/, Resources/, ActivityData/, EmailToWorker/, Widgets/)
│   └── Worker/ (Dashboard, Projects/, Submissions/, Receipts/, Resources/, Shareholders/)
├── Models/ (User, Project, Shareholder, Submission, Receipt, Resource, Option,
│            EsignonShareholder, EmailJob, EmailRecipient, EmailLink, EmailAttachment)
├── Services/ (Reports/, Email/, Esignon/, Files/ — phase 6/7)
└── Support/ (SanitizeTitle — phase 6)
resources/
├── templates/ (email-to-worker.xlsx, worker_shareholder_template.xlsx — phase 6)
├── fonts/nanum-gothic/ (phase 6)
└── views/
    ├── layouts/ (admin.blade.php, worker.blade.php)
    └── livewire/ (admin/…, worker/…)
routes/ (web.php, admin.php, worker.php, settings.php, console.php)
```

**Schema notes**
- Supabase `project` and storage `project` merge into one `projects` table; every imported table keeps `legacy_id` (users also `supabase_uuid`) so the import command is idempotent.
- `submission.is_deleted` and `receipt.status` (inverted boolean — verify polarity in `src/hooks/useReceipt.js` during import) both become `deleted_at` (SoftDeletes).
- `submission.user_name` denormalization is kept for historic display.
- Relationships: Project hasMany Shareholder/Submission/Resource/EmailJob/EsignonShareholder; Shareholder hasMany Submission; User hasMany Submission/Receipt; EmailJob hasMany EmailRecipient/EmailLink/EmailAttachment.

---

## 12. Step-by-step migration order

1. **Foundation** *(current phase)* — schema migrations, models, role middleware, admin/worker route groups, empty Livewire skeletons, admin/worker layouts, filesystem disks, design tokens. LibreOffice added to Lando.
2. **Data import** — `php artisan legacy:import` (a `pgsql` connection to Supabase + second `mysql` connection to `leenmore_storage`; idempotent upserts by `legacy_id` in dependency order: users → projects → shareholders → submissions → receipts → resources → options → esignon_shareholders → email\_\*) and `legacy:import-files` (copy upload dirs into disks, verify every DB attachment path resolves).
3. **Auth** — role-aware post-login redirect (admin → `/dashboard`, worker → `/app`), imported users with forced password reset, disable public registration.
4. **Worker flows** — read pages first (dashboard, project, resources, receipts, search), then writes (submission form, receipt submit) with `UploadService`.
5. **Admin flows** — full admin area including shareholder import, exports, zips, realtime-results polling widget.
6. **Storage services & crons** — eSignon sync (verify dedup-hash parity so re-sync doesn't duplicate), report generator (golden-file test). Legacy crons stay on until cutover.
7. **Emails** — compose flow → `EmailQueueService` → `GenerateWorkerReport` → `SendEmailBatch`; test on log/Mailpit mailer first.
8. **Cutover & retirement** — freeze legacy crons, final idempotent re-import, switch hosts, enable scheduler + queue worker, one verification week with legacy read-only, then retire Supabase, leenmore-storage, and the React host.

### Risks / gotchas

- **Korean filenames/paths:** UTF-8 NFC vs NFD normalization; zip entry encoding for Windows users (match legacy `ZipArchiver.php` behavior); consent dir renamed to ASCII root but Korean filenames preserved.
- **LibreOffice:** must exist in Lando and production; concurrent runs need per-run `-env:UserInstallation`; keep mPDF + NanumGothic fallback working.
- **ReportGenerator fidelity:** port mechanically, golden-file test.
- **eSignon:** credentials to `config/services.php`; dedup hash + Korean CSV header parsing must match exactly; `link_manage_id` must survive the projects-table merge.
- **Passwords:** reset-on-first-login requires working SMTP *before* cutover; pre-warn users.
- **ID remapping:** all FKs remapped during import; `legacy_id` keeps join keys.
- **Timezone:** receipts are KST-dated; recommend `app.timezone = Asia/Seoul` to preserve behavior.
- **Sanitize-title drift:** ported slugification must byte-match or zips/downloads 404 on migrated files.
- **Dual-write gap:** between staging and cutover, production writes go to Supabase only; freeze + final re-import closes the gap.
- **Unknown callers:** confirm nothing else hits the `AUTH_TOKEN` endpoints before retiring leenmore-storage.
