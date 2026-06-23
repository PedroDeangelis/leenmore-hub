<?php

namespace App\Exports;

use App\Imports\ShareholderImporter;
use App\Models\ProjectShareholder;

/**
 * Turns a project's shareholder roster back into the firm's CSV layout — the
 * exact column order {@see ShareholderImporter} reads — so an exported file can
 * be re-imported as a round-trip. Each assignment maps to one data row; the
 * per-project contact/address overrides win over the person's base values, and
 * the slash-joined 활동가 names mirror the importer's worker parsing.
 */
class ShareholderExporter
{
    /**
     * The header row — identical to the importable template.
     *
     * @return array<int, string>
     */
    public function headers(): array
    {
        return ShareholderImporter::templateHeaders();
    }

    /**
     * One CSV row for an assignment, ordered to match {@see headers()}.
     *
     * @return array<int, string>
     */
    public function row(ProjectShareholder $assignment): array
    {
        $person = $assignment->shareholder;

        $values = [
            'no' => (string) ($assignment->no ?? ''),
            'registration' => $this->registration($person?->registration),
            'sex' => $person?->sex ?? '',
            'name' => $person?->name ?? '',
            'shares' => $this->number($assignment->shares),
            'shares_total' => $this->number($assignment->shares_total),
            'electronic_voting' => $this->bool($assignment->electronic_voting),
            'address' => $assignment->effective_address ?? '',
            'contact_info' => $assignment->effective_contact ?? '',
            'contact_info_2' => $assignment->contact_info_2 ?? '',
            'source_database' => $assignment->source_database ?? '',
            'contact_worker' => $assignment->contact_worker ?? '',
            'worker_names' => $assignment->workers->pluck('name')->join(' / '),
            'prev_result' => $assignment->prev_result ?? '',
            'prev_comment' => $assignment->prev_comment ?? '',
            'prev_note' => $assignment->prev_note ?? '',
            'api_recipient_contact' => $assignment->api_recipient_contact ?? '',
            'api_recipient_completion_date' => $assignment->api_recipient_completion_date?->toDateString() ?? '',
        ];

        return array_map(fn (string $field): string => $values[$field] ?? '', ShareholderImporter::fields());
    }

    /**
     * Re-hyphenate a 13-digit personal RRN (YYMMDD-Sxxxxxx); leave corporate /
     * partial numbers as stored.
     */
    private function registration(?string $digits): string
    {
        if ($digits === null || $digits === '') {
            return '';
        }

        return strlen($digits) === 13
            ? substr($digits, 0, 6).'-'.substr($digits, 6)
            : $digits;
    }

    private function number(?int $value): string
    {
        return $value === null ? '' : number_format($value);
    }

    private function bool(?bool $value): string
    {
        return match ($value) {
            true => 'Y',
            false => 'N',
            null => '',
        };
    }
}
