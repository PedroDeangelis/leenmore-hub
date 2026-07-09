<?php

namespace App\Enums;

use App\Models\ProjectShareholder;

/**
 * The columns a 명부양식 (worker roster form) can be ordered by — the port of
 * the legacy compose page's Sort Column dropdown (options a–k). Each case maps
 * to the roster value it sorts on; 연번 and 총소유주식수 compare numerically,
 * everything else compares as locale-aware text. Labels are the Korean domain
 * headers the firm uses (hardcoded like the legacy app and the other exports,
 * not translated).
 */
enum RosterSortColumn: string
{
    case No = 'no';

    case Identifier = 'identifier';

    case Sex = 'sex';

    case Name = 'name';

    case PrevNote = 'prev_note';

    case SharesTotal = 'shares_total';

    case Address = 'address';

    case Contact = 'contact_info';

    case SourceDatabase = 'source_database';

    case PrevResult = 'prev_result';

    case PrevComment = 'prev_comment';

    /**
     * The Korean column header, exactly as on the legacy compose page.
     */
    public function label(): string
    {
        return match ($this) {
            self::No => '연번',
            self::Identifier => '고유번호',
            self::Sex => '성별',
            self::Name => '주주명',
            self::PrevNote => '비고',
            self::SharesTotal => '총소유주식수',
            self::Address => '주소',
            self::Contact => '주소서치',
            self::SourceDatabase => '구연락처',
            self::PrevResult => '구 판단',
            self::PrevComment => '구 멘트',
        };
    }

    /**
     * Whether this column compares numerically (legacy columns a/f) rather
     * than as text.
     */
    public function isNumeric(): bool
    {
        return $this === self::No || $this === self::SharesTotal;
    }

    /**
     * The sortable value for one roster assignment row.
     */
    public function valueFor(ProjectShareholder $assignment): string
    {
        return (string) match ($this) {
            self::No => $assignment->no ?? '',
            self::Identifier => ($assignment->shareholder?->name ?? '').($assignment->shareholder?->date_of_birth_code ?? ''),
            self::Sex => $assignment->shareholder?->sex ?? '',
            self::Name => $assignment->shareholder?->name ?? '',
            self::PrevNote => $assignment->prev_note ?? '',
            self::SharesTotal => $assignment->shares_total ?? '',
            self::Address => $assignment->effective_address ?? '',
            self::Contact => $assignment->effective_contact ?? '',
            self::SourceDatabase => $assignment->source_database ?? '',
            self::PrevResult => $assignment->prev_result ?? '',
            self::PrevComment => $assignment->prev_comment ?? '',
        };
    }
}
