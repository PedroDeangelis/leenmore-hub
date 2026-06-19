<?php

namespace App\Support;

use App\Enums\PersonType;
use Illuminate\Support\Carbon;

/**
 * Derives a shareholder's identity from their 실명번호 (real-name number).
 *
 * - A 13-digit individual RRN (YYMMDD-Sxxxxxx) yields the birth code (first six
 *   digits), the rest as `code`, and a best-effort date of birth using the RRN
 *   century digit (the first digit of the suffix).
 * - A shorter numeric is treated as a corporation's business number — no DOB.
 *
 * Mirrors the legacy mapper (getFormatedShareholders.js).
 */
final readonly class KoreanRegistration
{
    public string $digits;

    public function __construct(string $raw)
    {
        $this->digits = preg_replace('/\D/', '', $raw) ?? '';
    }

    public static function from(string $raw): self
    {
        return new self($raw);
    }

    /**
     * True when there aren't enough digits to be a personal RRN.
     */
    public function isCorporation(): bool
    {
        return strlen($this->digits) < 6;
    }

    public function personType(): PersonType
    {
        return $this->isCorporation() ? PersonType::Corporation : PersonType::Individual;
    }

    /**
     * The 6-digit birth code for individuals; the whole number for corporations.
     */
    public function dateOfBirthCode(): string
    {
        return $this->isCorporation() ? $this->digits : substr($this->digits, 0, 6);
    }

    /**
     * The trailing portion of the RRN (individuals), or the whole number (corps).
     */
    public function code(): string
    {
        return $this->isCorporation() ? $this->digits : substr($this->digits, 6);
    }

    /**
     * Best-effort date of birth for individuals, using the RRN century digit.
     * Null for corporations or when the date is unparseable.
     */
    public function dateOfBirth(): ?Carbon
    {
        if ($this->isCorporation()) {
            return null;
        }

        $code = $this->dateOfBirthCode();
        $year = (int) substr($code, 0, 2);
        $month = (int) substr($code, 2, 2);
        $day = (int) substr($code, 4, 2);

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        $century = $this->century();
        $date = Carbon::createFromDate($century + $year, $month, $day)->startOfDay();

        // Reject impossible dates (e.g. 02/30 rolling over).
        return $date->month === $month && $date->day === $day ? $date : null;
    }

    /**
     * Century base from the RRN gender/century digit (first digit of the suffix).
     */
    private function century(): int
    {
        $marker = $this->code()[0] ?? '';

        return match ($marker) {
            '3', '4', '7', '8' => 2000,
            '9', '0' => 1800,
            default => 1900,
        };
    }
}
