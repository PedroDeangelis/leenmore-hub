<?php

namespace App\Services\Esignon;

use App\Models\EsignonShareholder;
use App\Models\Project;
use App\Models\ProjectShareholder;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Drives the two halves of the ESIGNON (전자위임) sync, the Laravel port of the
 * legacy leenmore-storage `LinkService` + `ShareholderSupabaseSync`:
 *
 *  - {@see pull()} downloads a project link's completed documents and upserts
 *    them into `esignon_shareholders`.
 *  - {@see match()} matches each unsynced signer to a roster assignment (by name
 *    + 생년월일 6자리) and writes `api_recipient_*` onto `project_shareholders`.
 *    Signers with no roster match stay flagged — the "주주명부 누락" set.
 */
class EsignonSync
{
    public function __construct(private readonly EsignonClient $client) {}

    /**
     * Pull and upsert a project's completed ESIGNON documents. New or changed
     * rows are (re)flagged `needs_sync`; unchanged rows are left untouched.
     *
     * @return int the number of documents pulled
     */
    public function pull(Project $project): int
    {
        $linkManageId = (string) ($project->link_manage_id ?? '');

        if ($linkManageId === '') {
            return 0;
        }

        $records = $this->client->download($linkManageId);
        $count = 0;

        foreach ($records as $record) {
            $linkId = trim((string) ($record['link_id'] ?? ''));

            if ($linkId === '') {
                continue;
            }

            $name = trim((string) ($record['name'] ?? ''));
            $identifier = $this->normalizeDob((string) ($record['dob'] ?? ''));
            $contact = $this->normalizeContact((string) ($record['contact'] ?? ''));
            $completedDate = $this->normalizeDate((string) ($record['completed_date'] ?? ''));

            $hash = hash('sha256', (string) json_encode([
                'identifier' => $identifier,
                'name' => $name,
                'contact' => $contact,
                'completed_date' => $completedDate,
            ]));

            $count++;

            $existing = EsignonShareholder::query()
                ->where('project_id', $project->id)
                ->where('link_id', $linkId)
                ->first();

            // Unchanged since last pull — leave its match state alone.
            if ($existing !== null && $existing->data_hash === $hash) {
                continue;
            }

            EsignonShareholder::updateOrCreate(
                ['project_id' => $project->id, 'link_id' => $linkId],
                [
                    'link_manage_id' => $linkManageId,
                    'identifier' => $identifier,
                    'name' => $name,
                    'contact' => $contact,
                    'completed_date' => $completedDate,
                    'data_hash' => $hash,
                    'needs_sync' => true,
                ],
            );
        }

        return $count;
    }

    /**
     * Match the project's unsynced signers to roster assignments, writing the
     * two `api_recipient_*` fields onto every matching assignment.
     *
     * @return array{matched: int, missing: int}
     */
    public function match(Project $project): array
    {
        $pending = EsignonShareholder::query()
            ->where('project_id', $project->id)
            ->where('needs_sync', true)
            ->get();

        $matched = 0;
        $missing = 0;

        foreach ($pending as $signer) {
            $assignments = ProjectShareholder::query()
                ->where('project_id', $project->id)
                ->whereHas('shareholder', function (Builder $query) use ($signer): void {
                    $query->where('name', $signer->name)
                        ->where('date_of_birth_code', $signer->identifier);
                })
                ->get();

            if ($assignments->isEmpty()) {
                $missing++;

                continue;
            }

            foreach ($assignments as $assignment) {
                $assignment->update([
                    'api_recipient_contact' => $signer->contact,
                    'api_recipient_completion_date' => $signer->completed_date?->toDateString(),
                ]);
            }

            $signer->update(['needs_sync' => false, 'synced_at' => now()]);
            $matched++;
        }

        return ['matched' => $matched, 'missing' => $missing];
    }

    /**
     * Strip ESIGNON's ` UTC(+09:00)` suffix and normalize to `Y-m-d H:i:s`.
     */
    private function normalizeDate(string $rawDate): ?string
    {
        // e.g. "2026-02-06 12:11:56 UTC(+09:00)"
        if (trim($rawDate) === '') {
            return null;
        }

        $clean = preg_replace('/ UTC\(.+\)$/', '', $rawDate) ?? $rawDate;
        $timestamp = strtotime($clean);

        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    /**
     * First 6 digits of a date-of-birth string (생년월일 6자리).
     */
    private function normalizeDob(string $rawDob): string
    {
        if ($rawDob === '') {
            return '';
        }

        $digitsOnly = preg_replace('/\D/', '', $rawDob) ?? '';

        return substr($digitsOnly, 0, 6);
    }

    /**
     * Format a Korean phone number, preserving the original when it doesn't
     * match a known shape.
     */
    private function normalizeContact(string $rawContact): string
    {
        $contact = trim($rawContact);
        $digitsOnly = preg_replace('/\D/', '', $contact) ?? '';
        $len = strlen($digitsOnly);

        // Mobile: 010, 011, 016~019 (10~11 digits)
        if (preg_match('/^01[0-9]/', $digitsOnly) === 1) {
            if ($len === 11) {
                return preg_replace('/(\d{3})(\d{4})(\d{4})/', '$1-$2-$3', $digitsOnly);
            }
            if ($len === 10) {
                return preg_replace('/(\d{3})(\d{3})(\d{4})/', '$1-$2-$3', $digitsOnly);
            }
        }

        // Seoul landline: 02 prefix (9~10 digits)
        if (preg_match('/^02/', $digitsOnly) === 1) {
            if ($len === 9) {
                return preg_replace('/(\d{2})(\d{3})(\d{4})/', '$1-$2-$3', $digitsOnly);
            }
            if ($len === 10) {
                return preg_replace('/(\d{2})(\d{4})(\d{4})/', '$1-$2-$3', $digitsOnly);
            }
        }

        // Other landlines: 0XX prefix (10~11 digits)
        if ($len === 10) {
            return preg_replace('/(\d{3})(\d{3})(\d{4})/', '$1-$2-$3', $digitsOnly);
        }
        if ($len === 11) {
            return preg_replace('/(\d{3})(\d{4})(\d{4})/', '$1-$2-$3', $digitsOnly);
        }

        // Toll-free / special: 1588-1234 (8 digits)
        if ($len === 8 && preg_match('/^1[5-9]/', $digitsOnly) === 1) {
            return preg_replace('/(\d{4})(\d{4})/', '$1-$2', $digitsOnly);
        }

        return $contact; // fallback: as-is
    }
}
