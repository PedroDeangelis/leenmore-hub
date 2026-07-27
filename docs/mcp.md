# Connecting an AI assistant (MCP)

Leenmore Hub exposes a **read-only MCP server** so ChatGPT, Claude and Gemini can answer questions
about live hub data — 판단 tallies, worker performance, receipt totals — without anyone exporting a
spreadsheet.

**There is one server, not one per assistant.** MCP is a shared standard: every client below points
at the same URL and the same tools. Adding a fourth assistant later costs nothing.

```
https://hub.<domain>/mcp/leenmore
```

---

## What it can and cannot do

**Read only.** Every tool is annotated read-only and none of them writes. Nothing can be created,
edited, archived or deleted through this route, by any client, ever. That is enforced by there being
no write tool to call — not by asking the model nicely.

**Admin only.** The endpoint is gated on the `use-mcp` ability in
[AppServiceProvider](../app/Providers/AppServiceProvider.php), which only `admin` holds. An office or
worker account that completes the OAuth flow still gets a 403. To open it up later, add the role to
that one line.

**Redacted.** Everything a tool returns is transmitted to OpenAI / Anthropic / Google and may be
retained by them, so [`App\Support\Pii`](../app/Support/Pii.php) sits between the database and every
response:

| Field | What the assistant sees |
|---|---|
| 실명번호 | `901231-1******` — birth code and century/sex digit only |
| Phone numbers | never sent; `has_contact: true/false` instead |
| Addresses | `서울특별시 강남구` — 시/도 · 시/군/구 only |
| Date of birth | the year alone |
| Activity report notes | excluded from every list; one record at a time via `fetch` |

There is no unmask tool and no flag to disable this. [RedactionTest](../tests/Feature/Mcp/RedactionTest.php)
fails the build if any of it regresses.

---

## Connecting each client

All three run an OAuth flow the first time: you'll be sent to the hub's login page, then to a consent
screen naming the client. Approve it once and the connection persists.

### Claude

Settings → Connectors → **Add custom connector** → paste the URL. Claude discovers the OAuth
configuration automatically; no client id or secret to fill in.

Available on Free, Pro, Max, Team and Enterprise, and in Claude Desktop. Note the connection is made
**from Anthropic's servers**, not from your machine — the endpoint has to be reachable from the
public internet, which the Hostinger subdomain is.

### ChatGPT

Settings → Connectors → **Developer mode** → Add → paste the URL. Developer mode is a beta and needs
Plus, Pro, Business, Enterprise or Edu. On Business/Enterprise an admin can allowlist it org-wide.

The `search` and `fetch` tools exist specifically for ChatGPT: its deep research mode looks for that
exact pair by name. Don't rename them.

### Gemini

Gemini CLI, via `.gemini/settings.json`:

```json
{
  "mcpServers": {
    "leenmore": { "httpUrl": "https://hub.<domain>/mcp/leenmore" }
  }
}
```

Gemini Enterprise can add it as a custom MCP server data store instead. Two caveats: Gemini reads
`list_tools` only — it ignores MCP resources, so the glossary won't load there and it leans entirely
on the server instructions — and custom-connector support in the *consumer* gemini.google.com app is
the least certain of the three.

---

## The tools

| Tool | What it answers |
|---|---|
| `list-projects` | Which projects exist, their share targets and days to the meeting |
| `get-project` | One project's 판단 definitions and the full 판단 결과 현황 tally |
| `list-shareholders` | A project's roster, filterable by 판단, worker, judged state, or `proxy_only` |
| `list-submissions` | Activity report history by project, worker, 판단 or date range |
| `activity-data` | Per-worker 접촉 횟수, 위임 counts, shares covered, days worked |
| `list-receipts` | Receipts with the filtered total and a per-category breakdown |
| `list-users` | Staff and workers by role and active state |
| `search` / `fetch` | Find a record by name, then pull it in full |

Every list tool paginates: 50 rows by default, 200 maximum, always with a `total`. That cap is
deliberate — an unbounded "show me everyone" would otherwise ship the whole roster off-site.

`activity-data` and `get-project` are the important ones. They call the same
[ActivityData](../app/Support/ActivityData.php) and [JudgmentTally](../app/Support/JudgmentTally.php)
classes the 활동 데이터 page and the Excel exports use, so an assistant's numbers and the app's screens
cannot drift apart.

### One rule worth knowing

**위임 is not a single 판단 value — it is any 판단 whose colour is green.** A project defines several
(위임(대면_서명), 위임(비대면_녹취), …). Filtering on one label undercounts proxies badly. The server
instructions tell the model this, `list-shareholders` has a `proxy_only` flag that does it correctly,
and every result carries an explicit `is_proxy`.

---

## Working on it

```bash
php artisan mcp:inspector mcp/leenmore   # browse and call the tools interactively
php artisan test tests/Feature/Mcp       # tools, redaction, access
```

Adding a tool: `php artisan make:mcp-tool …`, annotate it `#[IsReadOnly]` with an explicit
`#[Name]`, route any personal data through `Pii`, and register it in
[LeenmoreServer](../app/Mcp/Servers/LeenmoreServer.php). A test globs the tools directory and fails
if the read-only annotation is missing, so a write tool cannot be added by accident.

## Revoking access

- **One client** — delete its row from `oauth_clients`, or revoke its tokens in `oauth_access_tokens`.
- **Everything, immediately** — remove `'use-mcp'` from `PERMISSIONS` in
  [AppServiceProvider](../app/Providers/AppServiceProvider.php). Every connected assistant starts
  getting 403s on its next call; no token cleanup needed.
