<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Redaction for anything leaving the app towards a third party — in practice the
 * MCP tools in app/Mcp, whose responses are transmitted to OpenAI / Anthropic /
 * Google and may be retained there.
 *
 * This is the security boundary for that egress, so it lives in one place and every
 * tool routes through it rather than each deciding what to trim. The rule is:
 * enough identity to reason about a person, never enough to contact or identify
 * them off-system.
 */
final readonly class Pii
{
    /** A 주민등록번호 is exactly this many digits; nothing else is one. */
    private const RRN_LENGTH = 13;

    /**
     * A 실명번호 reduced to its birth code plus the century/sex digit —
     * `901231-1******`.
     *
     * Only a genuine 13-digit RRN is split that way. Anything else — a 10-digit
     * corporate business number, a partial, a typo — keeps three digits and loses
     * the rest, because we cannot know which parts are safe. Note this is stricter
     * than KoreanRegistration::isCorporation(), whose "fewer than six digits"
     * heuristic exists to parse birth dates, not to decide what is safe to disclose.
     *
     * Returns null for null/blank input so callers can pass a nullable column
     * straight through.
     */
    public static function registration(?string $registration): ?string
    {
        if (blank($registration)) {
            return null;
        }

        $parsed = KoreanRegistration::from($registration);
        $digits = $parsed->digits;

        if ($digits === '') {
            return null;
        }

        if (Str::length($digits) !== self::RRN_LENGTH) {
            return Str::substr($digits, 0, 3).str_repeat('*', Str::length($digits) - 3);
        }

        // The century/sex digit carries no contact value but is what makes the
        // masked number recognisable to staff who already know the person.
        return $parsed->dateOfBirthCode().'-'.$parsed->code()[0].str_repeat('*', 6);
    }

    /**
     * An address cut back to its administrative prefix — `서울특별시 강남구`. Enough
     * to group people by region, not enough to visit them.
     */
    public static function address(?string $address): ?string
    {
        if (blank($address)) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($address), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $parts === [] ? null : implode(' ', array_slice($parts, 0, 2));
    }

    /**
     * The birth year alone. A full date of birth is an identifier; the year is a
     * demographic.
     */
    public static function birthYear(?CarbonInterface $dateOfBirth): ?int
    {
        return $dateOfBirth?->year;
    }

    /**
     * Whether a contact value exists, without disclosing it. Tools report
     * `has_contact` so "how many shareholders are unreachable?" stays answerable.
     */
    public static function hasContact(?string ...$contacts): bool
    {
        foreach ($contacts as $contact) {
            if (filled($contact)) {
                return true;
            }
        }

        return false;
    }
}
