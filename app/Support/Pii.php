<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Redaction for anything leaving the app — in practice the REST API (ApiData),
 * whose bearer tokens are handed to the client's dashboards and integrations.
 *
 * This is the security boundary for that egress, so it lives in one place and every
 * endpoint routes through it rather than each deciding what to trim. The rule is:
 * enough identity to reason about a person, never enough to contact or identify
 * them off-system.
 */
final readonly class Pii
{
    /** A 주민등록번호 is exactly this many digits; nothing else is one. */
    private const RRN_LENGTH = 13;

    /**
     * What a free-text note loses, in order, and what takes its place. Each
     * pattern refuses to start or end inside a longer run of digits, so it never
     * takes half of one and leaves the rest behind.
     *
     * @var array<string, string>
     */
    private const NOTE_REDACTIONS = [
        // 실명번호, with or without its hyphen — before phones, which it contains.
        '/(?<!\d)\d{6}\s?-?\s?\d{7}(?!\d)/u' => '[실명번호]',

        // …and one the worker half-masked themselves (901231-1******).
        '/(?<!\d)\d{6}\s?-?\s?\d{0,6}[*xX]+/u' => '[실명번호]',

        '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu' => '[이메일]',

        // Mobile (010…), area-coded landline (02, 031…), 070 and 050x numbers,
        // domestic or +82, separated by hyphens, dots, spaces or nothing.
        '/(?<![\d+])(?:\+?82[\s.\-]?\(?0?|\(?0)(?:1[016789]|2|[3-6]\d|70|50\d)\)?[\s.\-]?\d{3,4}[\s.\-]?\d{4}(?!\d)/u' => '[전화번호]',

        // 1588-1234 style national numbers.
        '/(?<!\d)1[5-9]\d{2}[\s.\-]?\d{4}(?!\d)/u' => '[전화번호]',

        // A local number written without its area code — but not a year range.
        '/(?<![\d.\-])(?!(?:19|20)\d{2}-(?:19|20)\d{2}(?!\d))\d{3,4}-\d{4}(?![\d.\-])/u' => '[전화번호]',

        // Anything else long enough to be a phone or account number. Spaces do not
        // join runs here (a date followed by a count would read as one number) and
        // neither do commas, so share counts like 2,720,000 survive.
        '/(?<![\d.\-])\d(?:[.\-]?\d){8,}(?![\d.\-])/u' => '[전화번호]',
    ];

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

    /**
     * A worker's free-text note with what makes it contactable taken out — phone
     * numbers, 실명번호 and email addresses become `[전화번호]`, `[실명번호]` and
     * `[이메일]`, and the rest of the text is left as written.
     *
     * Workers type phone numbers into these notes, so this errs towards redacting:
     * a long number that turns out to be harmless costs less than a phone number
     * that gets through.
     */
    public static function note(?string $note): ?string
    {
        if (blank($note)) {
            return null;
        }

        $redacted = trim((string) preg_replace(
            array_keys(self::NOTE_REDACTIONS),
            array_values(self::NOTE_REDACTIONS),
            $note,
        ));

        return $redacted === '' ? null : $redacted;
    }
}
