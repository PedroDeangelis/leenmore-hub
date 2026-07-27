<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

/**
 * The Korean domain vocabulary, offered as a resource so clients that support
 * resources can pull it on demand instead of carrying it in every prompt. The
 * rules that change how answers are *computed* live in the server's
 * #[Instructions] block instead, because Gemini reads tools only and ignores
 * resources.
 */
#[Uri('leenmore://glossary')]
#[Title('Korean terms used in Leenmore Hub')]
#[MimeType('text/markdown')]
#[Description('Glossary of the Korean domain terms that appear in tool arguments and results — 판단, 위임, 주주명부, 활동가 and the rest.')]
class GlossaryResource extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::text(<<<'MARKDOWN'
            # Leenmore Hub glossary

            ## People

            - **주주 (juju)** — shareholder. Either an individual or a corporation
              (`person_type`); corporations have a business number instead of a 실명번호.
            - **활동가 (hwaldongga)** — field worker. Users with the `worker` role. They visit
              assigned shareholders and file activity reports.
            - **실명번호 / 주민등록번호** — Korean resident registration number, `YYMMDD-Sxxxxxx`.
              The first six digits are the birth date; the first digit of the suffix encodes
              century and sex. Always returned masked as `901231-1******`.

            ## Documents and lists

            - **주주명부 (jujumyeongbu)** — the shareholder register: the roster of who holds how
              many shares for a given meeting. Imported per project.
            - **명부양식** — the printable per-worker roster form generated for field visits.
            - **영수증 (yeongsujeung)** — expense receipt submitted by a worker.
            - **사용 내역** — the receipt's usage category.

            ## Outcomes

            - **판단 (pandan)** — the judgment/outcome recorded for a shareholder. Project-defined,
              ordered, and colour-coded.
            - **위임 (wiim)** — proxy granted: the shareholder has authorised the campaign to vote
              their shares. Represented by *any* green 판단, never a single label. Variants name
              how it was obtained: 대면 (in person), 비대면 (remote), 서명 (signature),
              녹취 (recording), 문자 (SMS), 신분증 (ID document), 발급번호 (issued number).
            - **위임대기** — awaiting proxy. **전자위임** — electronic proxy, signed through the
              ESIGNON integration.
            - **유보 (yubo)** — reserved; the shareholder has not decided.
            - **단순부재 / 본인부재** — nobody home / the shareholder specifically was out.
            - **부재(콜)** — absent, followed up by phone.
            - **소재불명 / 주소불명** — whereabouts or address unknown.
            - **출입불가** — could not gain access to the building.

            ## Activity

            - **활동 데이터** — the per-worker analytics: contacts made, proxies obtained, shares
              covered, days worked. Served by the `activity-data` tool.
            - **접촉 횟수** — contact count. Counts *every* report including repeat visits to the
              same shareholder, whereas share and proxy figures dedupe to each shareholder's
              latest report. Expect the two to differ; that is correct, not a bug.
            - **판단 결과 현황** — the tally of shares behind each current 판단, grouped into
              colour bands and measured against the project's share target. Served by
              `get-project`.
            - **누계** — running total.
            MARKDOWN);
    }
}
