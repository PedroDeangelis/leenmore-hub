<?php

namespace App\Support\Supabase;

/**
 * Supabase stores every number as text, and the client's own UI writes them
 * with thousands separators: shares_issued is "259,498,284", a receipt amount
 * "14,000".
 *
 * This matters more than it looks. `(int) "259,498,284"` is 259 — PHP stops at
 * the first comma and reports no error — so a share total would be understated
 * by six orders of magnitude and still look like a plausible number. Every
 * numeric field crossing out of Supabase goes through here.
 */
final class Num
{
    /**
     * A whole number, or null when there is nothing meaningful to read.
     */
    public static function int(?string $value): ?int
    {
        $digits = self::clean($value);

        return $digits === null ? null : (int) $digits;
    }

    /**
     * A decimal — money, in practice. Kept as a float for JSON output.
     */
    public static function decimal(?string $value, int $precision = 2): ?float
    {
        $digits = self::clean($value);

        return $digits === null ? null : round((float) $digits, $precision);
    }

    /**
     * Strip separators and whitespace, and reject anything left that is not a
     * number — "" , "-", "N/A" and stray text all become null rather than 0,
     * because a missing value and a genuine zero are different facts.
     */
    private static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = str_replace([',', ' ', ' ', '　'], '', trim($value));

        return is_numeric($cleaned) ? $cleaned : null;
    }
}
