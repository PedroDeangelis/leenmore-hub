<?php

namespace App\Livewire\Concerns;

use App\Models\Project;
use App\Models\ProjectResult;
use App\Models\ProjectShareholder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The shared activity-report form: the state, validation, file handling and
 * contact helpers used by both the field worker ([Worker\ActivityReport]) and
 * the admin manual-entry page ([Activity\Report]). Each component keeps its own
 * save()/mount() flow but delegates the common pieces here.
 */
trait ActivityReportForm
{
    public string $date = '';

    public ?int $resultId = null;

    /** @var array<int, array<int, string>> Repeatable phone rows, each split into 3 segments (000-0000-0000). */
    public array $contacts = [['', '', '']];

    public bool $privacyConsent = false;

    /** @var array<int, TemporaryUploadedFile> Consent-form uploads (only kept while privacyConsent is on). */
    public array $consentFiles = [];

    public ?string $note = null;

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    public function addContact(): void
    {
        $this->contacts[] = ['', '', ''];
    }

    public function removeContact(int $index): void
    {
        unset($this->contacts[$index]);

        // Keep at least one row, and re-index so wire:model bindings stay contiguous.
        $this->contacts = array_values($this->contacts) ?: [['', '', '']];
    }

    /**
     * Drop any staged consent files when the consent box is unticked.
     */
    public function updatedPrivacyConsent(bool $value): void
    {
        if (! $value) {
            $this->reset('consentFiles');
        }
    }

    /**
     * The validation rules for the form.
     *
     * @param  array<int, int>  $resultIds  the project's result ids
     * @return array<string, mixed>
     */
    protected function reportRules(array $resultIds): array
    {
        return [
            'date' => ['required', 'date'],
            'resultId' => ['required', Rule::in($resultIds)],
            'contacts' => ['array'],
            'contacts.*' => ['array'],
            'contacts.*.*' => ['nullable', 'string', 'max:10', 'regex:/^\d*$/'],
            'privacyConsent' => ['boolean'],
            'consentFiles' => ['array'],
            'consentFiles.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,heic,webp,pdf'],
            'note' => ['nullable', 'string', 'max:65535'],
            'attachments' => ['array'],
            'attachments.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,heic,webp,pdf,mp3,m4a,wav'],
        ];
    }

    /**
     * Enforce the chosen 판단's contact / attachment requirements. When editing,
     * an attachment already stored on the report (kept, not re-uploaded) counts
     * towards the requirement — pass its count as $existingAttachmentCount.
     *
     * @param  Collection<int, string>  $contacts
     */
    protected function requireContactAndAttachment(ProjectResult $result, Collection $contacts, int $existingAttachmentCount = 0): void
    {
        if ($result->contact_required && $contacts->isEmpty()) {
            throw ValidationException::withMessages([
                'contacts' => __('Contact is required for this result.'),
            ]);
        }

        if ($result->attachment_required && $this->attachments === [] && $existingAttachmentCount === 0) {
            throw ValidationException::withMessages([
                'attachments' => __('An attachment is required for this result.'),
            ]);
        }
    }

    /**
     * Store the staged attachment + consent uploads on the local disk.
     *
     * @return array{0: array<int, string>, 1: array<int, string>} [attachmentPaths, consentPaths]
     */
    protected function storeFiles(int $projectShareholderId): array
    {
        $paths = collect($this->attachments)
            ->map(fn (TemporaryUploadedFile $file): string => $file->store("submissions/{$projectShareholderId}", 'local'))
            ->all();

        $consentPaths = $this->privacyConsent
            ? collect($this->consentFiles)
                ->map(fn (TemporaryUploadedFile $file): string => $file->store("submissions/{$projectShareholderId}/consent", 'local'))
                ->all()
            : [];

        return [$paths, $consentPaths];
    }

    /**
     * Each phone row collapsed to a single "000-0000-0000" string, empties dropped.
     *
     * @return Collection<int, string>
     */
    protected function filledContacts(): Collection
    {
        return collect($this->contacts)
            ->map(function (mixed $parts): string {
                $parts = array_filter(
                    array_map(fn (mixed $part): string => trim((string) $part), (array) $parts),
                    fn (string $part): bool => $part !== '',
                );

                return implode('-', $parts);
            })
            ->filter()
            ->values();
    }

    /**
     * Split a stored contact string ("010-1234-5678, 02-987-6543") back into
     * editable 3-segment rows.
     *
     * @return array<int, array<int, string>>
     */
    protected function parseContacts(?string $contact): array
    {
        $rows = collect(explode(',', (string) $contact))
            ->map(fn (string $phone): string => trim($phone))
            ->filter()
            ->map(function (string $phone): array {
                $parts = array_pad(array_slice(explode('-', $phone), 0, 3), 3, '');

                return [(string) $parts[0], (string) $parts[1], (string) $parts[2]];
            })
            ->values()
            ->all();

        return $rows ?: [['', '', '']];
    }

    /**
     * Re-point the shareholder's current 판단 (result_id + last_note) at its most
     * recent remaining submission, or clear it when none remain. Keeps the
     * shareholder list and 판단 결과 현황 tally consistent after an edit/delete.
     */
    protected function syncCurrentResult(Project $project, ProjectShareholder $projectShareholder): void
    {
        $latest = $projectShareholder->submissions()->first();
        $resultId = $latest ? $project->results->firstWhere('name', $latest->result)?->id : null;

        $projectShareholder->update([
            'result_id' => $resultId,
            'last_note' => $latest?->note,
        ]);
    }
}
