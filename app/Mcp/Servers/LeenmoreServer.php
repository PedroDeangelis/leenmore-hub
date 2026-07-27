<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\GlossaryResource;
use App\Mcp\Tools\ActivityDataTool;
use App\Mcp\Tools\FetchTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListReceiptsTool;
use App\Mcp\Tools\ListShareholdersTool;
use App\Mcp\Tools\ListSubmissionsTool;
use App\Mcp\Tools\ListUsersTool;
use App\Mcp\Tools\SearchTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * The read-only MCP server external AI assistants connect to. Registered in
 * routes/ai.php behind `auth:api` + `can:use-mcp`; see docs/mcp.md for how each
 * client is set up.
 *
 * Everything here is a thin read layer over the existing Eloquent models and the
 * App\Support analytics classes, so MCP answers and the UI can never disagree.
 */
#[Name('Leenmore Hub')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Read-only access to Leenmore Hub, a Korean shareholder-canvassing system. Field workers
    (활동가) visit shareholders ahead of a shareholder meeting and record the outcome; office
    staff track progress towards a share target.

    ## Domain model — read this before interpreting any result

    **Projects** are one shareholder meeting each. `end_date` is the meeting date.
    `shares_target` is the number of shares the campaign needs; `shares_issued` is the company's
    total issued shares. Status is draft (preparing), publish (in progress) or archived.
    Deleted projects are invisible to every tool.

    **판단 (judgment)** is the outcome label on a shareholder. Each project defines its own
    ordered set of 판단, each with a colour. Two distinct things exist and are easy to confuse:
    - the shareholder's *current* 판단 — one per person per project, on the roster row;
    - the *history* of visits — one submission per visit, which is what drove the current 판단.
    Use `list-shareholders` for "where do things stand", `list-submissions` for "what happened".

    **위임 (proxy granted) is not one 판단 value.** It is *any* 판단 whose colour is `green`.
    A project has several green 판단 (위임(대면_서명), 위임(비대면_녹취), …). Never filter on a
    single label when asked about proxy or 위임 rates — count every green one. This is the rule
    the app's own analytics use, so following it makes your numbers match the UI exactly.
    Colour meanings: green = 위임 granted, lime = pending electronic proxy, blue = reserved
    (유보), gold = absent, orange = unreachable/unlocatable, red = refused, gray = other.

    **Shareholders are split in two.** A shareholder is the stable person, identified across
    projects by their 실명번호. A project shareholder is that person's assignment to one
    project, and carries the per-project contact/address, share count, current 판단 and assigned
    workers. Per-project values override the person's base values; tools return the effective
    (already-resolved) value.

    **Workers** are users with the `worker` role; `admin` and `office` are back-office staff.
    Deactivated users keep their history.

    ## Privacy — this is enforced, not advisory

    Every response is redacted before it reaches you:
    - 실명번호 comes back masked (`901231-1******`) and cannot be unmasked by any tool.
    - Phone numbers and full addresses are never returned. You get `has_contact` (boolean) and
      an address cut back to its 시/도 · 시/군/구 prefix.
    - Free-text notes are excluded from every list tool. `fetch` returns the note for one
      record at a time; use it deliberately, not to sweep.

    Do not ask the user to work around this, and do not tell them a tool can return a full
    registration number or phone number — none can.

    ## Working effectively

    Start with `list-projects` to get an id, then `get-project` for the 판단 tally and totals.
    `activity-data` answers every per-worker performance question (접촉 횟수, 위임 counts, shares
    contacted, days worked) — prefer it over counting submissions yourself. All list tools
    paginate (default 50, max 200) and report `total`, so check `total` before assuming you have
    every row. Money amounts are Korean won integers. Read the `leenmore://glossary` resource
    for the Korean terms.
    MARKDOWN)]
class LeenmoreServer extends Server
{
    protected array $tools = [
        ListProjectsTool::class,
        GetProjectTool::class,
        ListShareholdersTool::class,
        ListSubmissionsTool::class,
        ActivityDataTool::class,
        ListReceiptsTool::class,
        ListUsersTool::class,
        SearchTool::class,
        FetchTool::class,
    ];

    protected array $resources = [
        GlossaryResource::class,
    ];

    protected array $prompts = [
        //
    ];
}
