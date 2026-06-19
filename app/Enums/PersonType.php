<?php

namespace App\Enums;

/**
 * Whether a shareholder is a natural person or a legal entity. Mirrors the
 * legacy `shareholder.person_type` values.
 */
enum PersonType: string
{
    case Individual = 'individual';

    case Corporation = 'corporation';

    /**
     * Human-readable, translatable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Individual => __('Individual'),
            self::Corporation => __('Corporation'),
        };
    }
}
