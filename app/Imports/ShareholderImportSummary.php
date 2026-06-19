<?php

namespace App\Imports;

/**
 * The outcome of a roster import: how many assignments were newly created,
 * updated in place, and how many rows were skipped as unusable.
 */
final readonly class ShareholderImportSummary
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
    ) {}

    public function total(): int
    {
        return $this->created + $this->updated + $this->skipped;
    }
}
