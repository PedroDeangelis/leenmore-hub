# Smart Database Review

Product/architecture review of [FINAL_DATABASE_SCHEMA_PROPOSAL.md](FINAL_DATABASE_SCHEMA_PROPOSAL.md) and [DATABASE_AUDIT_AND_STRATEGY.md](DATABASE_AUDIT_AND_STRATEGY.md) against the actual code in `leenmore-app`. Goal: a smarter, more maintainable Laravel database — without making the first migration risky.

**New code evidence found during this review (resolves two open questions):**

- `last_name` is **never used anywhere** in the React app — zero hits across all JSX/JS. `first_name` appears in 18 files (forms, headers, email sender, user tables).
- The `shareholder.user` array holds **worker first names, not uuids.** It is populated from the import spreadsheet's `worker`/`활동가` column, split on `/` (`leenmore-app/src/pages/Admin/AddNewProject/components/getFormatedShareholders.js:141-147`, `pages/Admin/SingleProject/components/setShareholderUpdateList.js:63-85`). Every assignment query matches by first name: `SearchShareholdersApp/index.jsx:137-144` passes `usermeta.first_name`, `hooks/useShareholder.js:227,319,389` use `.contains("user", [user_name])`. The uuids in `SUPABASE_SAMPLE_DATA.md` were a sanitization artifact.
- The `prev_result` / `prev_comment` / `prev_note` columns are filled **from Korean spreadsheet columns** (`구 판단`, `구 멘트`, `비고`) at project-creation time (`getFormatedShareholders.js:49-51`, `setShareholderUpdateList.js:46-48`). In other words: **the client already carries shareholder history between projects manually, via Excel.** This is the strongest product argument for cross-project shareholder intelligence — it would automate an existing manual workflow.
- A cross-project search already half-exists: `useShareholderSearchByUser` (`hooks/useShareholder.js:375-425`) searches `registration`, `name`, `contact_worker` (ilike) across all projects the worker appears in.

---

## 1. User names

**Current state:** `profiles` has `first_name` + `last_name`; the business never uses last names and the code confirms it (`last_name`: 0 usages). More importantly, `first_name` is not just display — it is the **assignment and matching key** for the `shareholder.user` array and the search flows above.

**Options:**

| Option | Tradeoff |
|---|---|
| `name` only | Matches every actual usage. One canonical string. Import = `first_name`. Risk: if any profile has meaningful `last_name` data, it would be silently dropped. |
| `first_name` + `last_name` | Faithful copy, but carries a dead column forever and forces every future query/blade to decide which field to show — the exact ambiguity we want to remove. |
| `name` + optional split fields | Solves a problem this app doesn't have (no formal correspondence, no sorting by family name in code). Extra columns with no consumer. |

**Recommendation: single `name` column.** Import rule: `name = trim(first_name)`; during import, log any profile whose `last_name` is non-empty so it can be eyeballed once (expected: none or noise). The Fortify starter already uses a single `name` (`leenmore-hub/app/Models/User.php` — `initials()` works on it), so this also means zero changes to existing scaffold code.

This closes open question §10.6 of the strategy doc.

## 2. Shareholder structure: per-project vs global identity

**Current state:** each project's shareholders are independent rows, re-imported from Excel per project. History travels between projects only as the manually-prepared `prev_*` spreadsheet columns. The same person in 3 projects = 3 unrelated rows.

**Is a global `shareholders` + `project_shareholders` split worth doing now?**

What the split would enable is real (see §3) — but the source data makes it dangerous as a *migration step*:

1. **There is no global key in the legacy data.** Identity would have to be *inferred* by fuzzy matching (name + birth code + registration), and any wrong merge corrupts two people's histories permanently. Korean names collide often; `date_of_birth_code` is 6 digits (birthdate collisions are routine at this scale).
2. **The import becomes a data-science task instead of a copy task.** Idempotency, row-count verification, and "compare against the live React app" all stop being straightforward, exactly when we need them to validate the cutover.
3. **Every Livewire page in Phases 4–5 doubles its query depth** (`project_shareholders` ↔ `shareholders` joins) while we are trying to faithfully reproduce React screens that think in per-project rows.
4. The audit's stated principle is *preserve current behavior, migrate incrementally*. Identity resolution is a feature, not a migration.

**However** — the split should be *designed for now*, because the product value is clearly there (the client already wants this — they emulate it in Excel).

## 3. Smart shareholder finder (cross-project search)

The good news: **Option A's schema already supports this page.** Because all shareholder rows live in one MySQL table with typed, indexed columns, a "Search shareholder across all projects" page is a straight query — no schema change needed:

- **Find:** `shareholders` where `name LIKE`, `date_of_birth_code =`, `registration LIKE`, `contact_info LIKE` — served by the proposed indexes plus one addition (see §7): a composite `(name, date_of_birth_code)` index.
- **Group:** collapse hits by identity key `(name, date_of_birth_code)` (fallback `registration` for corporations) in PHP — "this person appeared in projects X, Y, Z".
- **Per project:** each grouped row *is* the project-specific record — `result` (latest, denormalized), `shares`, assigned workers via `shareholder_user`, full `submissions` history (`shareholder_id` FK), eSignon state via `api_recipient_*` columns and `esignon_shareholders` (matched on name + birth code, same as the legacy sync).
- Receipts are worker-scoped, not shareholder-scoped, so they don't belong on this page.

This is strictly better than the legacy version of the page (which only searched projects the current worker belonged to, 10-result limit, three ilike fields) and it ships in the first version with zero migration risk. If/when global identity lands later (§6), the page's "group by" simply becomes a join on `person_id` — the UI doesn't change.

## 4. Option A vs Option B

| | **Option A — current proposal** (`projects` → `shareholders` → `shareholder_user`, `submissions` → shareholder) | **Option B — global identity** (`shareholders` global → `project_shareholders` → `project_shareholder_user`, `submissions` → project_shareholder) |
|---|---|---|
| **Benefits** | 1:1 with legacy data → trivially verifiable import; pages mirror React queries; finder still possible (§3) | Deduplicated identity; automatic history across projects (replaces the Excel `prev_*` workflow); cleaner long-term model |
| **Risks** | Duplicate person rows persist (status quo); identity grouping is heuristic at query time | Wrong merges corrupt data invisibly; no legacy key to import against; verification vs live app becomes hard |
| **Migration complexity** | Low — copy + remap FKs + explode one array | High — fuzzy matching engine, manual-review queue, and a two-level FK remap *before* the app even runs |
| **Livewire pages** | Queries match the React mental model (per-project rows) | Every shareholder page joins two tables; worker flows need "participation" context everywhere |
| **Import logic** | Idempotent upserts on `supabase_id`; row counts must match source | Idempotency requires stable match decisions across re-runs (a re-run must not re-merge differently); row counts intentionally won't match |
| **Long-term maintainability** | Good, with a known dedup debt | Best — if the matching was right |

## 5. Matching strategy (for when global identity is introduced)

- **Exact match (auto-link):**
  - `registration` equal and non-empty — strongest key (registration numbers are issued identifiers), but only within the same `person_type` (an individual and a corporation never merge).
  - `name` + `date_of_birth` (full date, when present) — strong for individuals.
- **Possible match (auto-suggest, human confirms):**
  - `name` + `date_of_birth_code` (6-digit code only — two people born the same day with the same name is realistic across thousands of rows).
  - `name` + normalized phone (`contact_info`/`contact_info_2`, normalized: strip `-`/spaces, unify `+82`→`0`).
  - `name` + normalized address.
- **Manual review only (never auto-anything):** name-only matches; conflicting signals (same name+birth code but different registration).
- **Avoiding wrong merges:**
  - Normalize before comparing: Unicode NFC (Korean), trim, collapse whitespace.
  - Require agreement of ≥2 independent fields for any automatic link; one contradicting strong field (different `registration`, different `date_of_birth`) vetoes the match.
  - Links are **reversible**: store match provenance (`matched_by: registration|name_dob|manual`, confidence, timestamp) on the link row, never physically merge rows — so a bad link is an UPDATE, not a data loss.
  - Run the matcher as a backfill command producing a review report *before* any UI depends on it.

## 6. Recommended final direction

**Ship Option A for the first version, with three small "design-for-B" adjustments (§7). Introduce global shareholders as a post-cutover feature phase, not a migration phase.**

Reasoning: the migration's success test is "the hub behaves like the React app against the same data" — Option A keeps that test honest. Everything Option B offers can be added later as an additive change: a new `people` table + nullable `shareholders.person_id` + a matching backfill command with a manual review screen. Nothing in Option A blocks that path, and the smart finder (§3) delivers the most-wanted product value (cross-project lookup) in version one without any of the merge risk. Crucially, the `prev_*` Excel workflow keeps working exactly as today until the smarter replacement is proven.

## 7. Required changes to the existing documents

### `FINAL_DATABASE_SCHEMA_PROPOSAL.md`

1. **`users.name`** — remove **Needs confirmation**; rule: `name = first_name`; import logs non-empty `last_name` values for one-time review (§1).
2. **`shareholder_user` import note** — rewrite: array entries are **worker first names**, not uuids; pivot rows are derived by matching each name against `users.name`; unmatched or ambiguous names (two users with the same name) are logged, not guessed. Remove the uuid remap wording; this also closes strategy-doc question §10.3.
3. **`shareholders`: add `legacy_assigned_workers` json nullable** — verbatim copy of the legacy `user` array. Costs nothing, preserves import fidelity when name→user matching fails, and is the audit trail for the pivot derivation. (Also fixes today's fragility going forward: in Laravel, assignment is FK-based, so renaming a user no longer silently breaks their shareholder assignments — a real bug class in the legacy app.)
4. **`shareholders`: add composite index `(name, date_of_birth_code)`** — powers the smart finder grouping (§3) and the future matching backfill (§5).
5. **`shareholders` table description** — add one line: "Per-project rows by design; global person identity (`people` + nullable `person_id`) is a planned post-cutover addition — see SMART_DATABASE_REVIEW.md §6."

### `DATABASE_AUDIT_AND_STRATEGY.md`

1. **§10 open questions** — mark **#3 resolved** (names, with evidence paths above) and **#6 resolved** (single `name`); add a new confirmed risk: *duplicate first names among users would make pivot derivation ambiguous — check real `profiles` data for name collisions before import*.
2. **§6 type strategy** — change the uuid-array row: `shareholder.user` name array → pivot via `users.name` match (+ raw copy into `legacy_assigned_workers`).
3. **§11 phases** — add a post-cutover phase: "global shareholder identity (`people` table, matching backfill with manual review, finder upgrade)".
4. **§2 shareholder review** — note that `prev_*` columns are spreadsheet-imported history (evidence paths above), flagged as the workflow that global identity will eventually replace.

No changes needed to the other 12 tables, the import order, or the index strategy beyond the two additions above.
