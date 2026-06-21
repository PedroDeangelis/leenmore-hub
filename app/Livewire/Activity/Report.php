<?php

namespace App\Livewire\Activity;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Livewire\Concerns\ActivityReportForm;
use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Activity reports — step 3: an admin manually files reports for one shareholder
 * on behalf of a chosen worker (and may edit / delete existing ones). Reuses the
 * worker form via {@see ActivityReportForm}; reports are attributed to the
 * selected worker, with `created_by_user_id` recording the admin who entered it.
 * Admin + office (gated by `edit-submissions`).
 */
#[Title('Activity reports')]
class Report extends Component
{
    use ActivityReportForm;
    use WithFileUploads;

    #[Locked]
    public int $projectId;

    #[Locked]
    public int $projectShareholderId;

    /** The worker the report is attributed to. */
    public ?int $selectedWorkerId = null;

    /** The submission being edited, or null when creating a new one. */
    public ?int $editingId = null;

    /** Already-stored attachment paths kept while editing. @var array<int, string> */
    public array $existingAttachments = [];

    public function mount(Project $project, ProjectShareholder $projectShareholder): void
    {
        abort_unless($project->status === ProjectStatus::Publish, 404);
        abort_unless($projectShareholder->project_id === $project->id, 404);

        $this->projectId = $project->id;
        $this->projectShareholderId = $projectShareholder->id;
        $this->date = now()->toDateString();
    }

    /**
     * Load an existing report into the form for editing.
     */
    public function editReport(int $id): void
    {
        $project = Project::with('results')->findOrFail($this->projectId);
        $submission = $this->shareholder()->submissions()->findOrFail($id);

        $this->editingId = $submission->id;
        $this->selectedWorkerId = $submission->user_id;
        $this->date = ($submission->date ?? $submission->created_at)->toDateString();
        $this->resultId = $project->results->firstWhere('name', $submission->result)?->id;
        $this->contacts = $this->parseContacts($submission->contact);
        $this->note = $submission->note;
        $this->privacyConsent = (bool) $submission->privacy_consent;
        $this->existingAttachments = $submission->files ?? [];
        $this->attachments = [];
        $this->consentFiles = [];
        $this->resetValidation();
    }

    /**
     * Drop a kept attachment while editing (committed on save).
     */
    public function removeExistingAttachment(int $index): void
    {
        unset($this->existingAttachments[$index]);
        $this->existingAttachments = array_values($this->existingAttachments);
    }

    /**
     * Soft-delete a report, then re-sync the shareholder's current 판단.
     */
    public function deleteReport(int $id): void
    {
        $project = Project::with('results')->findOrFail($this->projectId);
        $projectShareholder = $this->shareholder();

        $projectShareholder->submissions()->findOrFail($id)->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        $this->syncCurrentResult($project, $projectShareholder);
        $this->dispatch('toast', message: __('Report deleted.'), variant: 'success');
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    /**
     * Validate and persist the report (create or update), attributing it to the
     * selected worker and recording the admin who entered it.
     */
    public function save(): void
    {
        $project = Project::with('results')->findOrFail($this->projectId);
        $projectShareholder = $this->shareholder();

        $rules = $this->reportRules($project->results->pluck('id')->all());
        $rules['date'] = ['required', 'date', 'before_or_equal:today'];
        $rules['selectedWorkerId'] = ['required', Rule::exists('users', 'id')->where('role', UserRole::Worker->value)];
        $this->validate($rules);

        $result = $project->results->firstWhere('id', $this->resultId);
        $contacts = $this->filledContacts();
        // A file kept from the original report satisfies the attachment requirement.
        $this->requireContactAndAttachment($result, $contacts, count($this->existingAttachments));

        $worker = User::findOrFail($this->selectedWorkerId);
        [$paths, $consentPaths] = $this->storeFiles($projectShareholder->id);

        $base = [
            'user_id' => $worker->id,
            'user_name' => $worker->name,
            'date' => $this->date,
            'result' => $result->name,
            'contact' => $contacts->isNotEmpty() ? $contacts->implode(', ') : null,
            'privacy_consent' => $this->privacyConsent,
            'note' => $this->note ?: null,
        ];

        if ($this->editingId !== null) {
            $submission = $projectShareholder->submissions()->findOrFail($this->editingId);
            $files = array_values(array_merge($this->existingAttachments, $paths));
            $consent = $this->privacyConsent
                ? array_values(array_merge($submission->privacy_consent_files ?? [], $consentPaths))
                : [];

            $submission->update($base + [
                'files' => $files !== [] ? $files : null,
                'privacy_consent_files' => $consent !== [] ? $consent : null,
            ]);
        } else {
            Submission::create($base + [
                'project_id' => $project->id,
                'project_shareholder_id' => $projectShareholder->id,
                'created_by_user_id' => auth()->id(),
                'files' => $paths !== [] ? $paths : null,
                'privacy_consent_files' => $consentPaths !== [] ? $consentPaths : null,
            ]);
        }

        $this->syncCurrentResult($project, $projectShareholder);
        $this->resetForm();
        $this->dispatch('toast', message: __('Report saved.'), variant: 'success');
    }

    public function render(): View
    {
        $project = Project::with('results')->findOrFail($this->projectId);
        $projectShareholder = ProjectShareholder::with(['shareholder', 'result'])->findOrFail($this->projectShareholderId);

        // The stored file paths of the report being edited, so each kept
        // attachment can link to the file-server route by its stable index.
        $editingFiles = $this->editingId !== null
            ? ($projectShareholder->submissions()->find($this->editingId)?->files ?? [])
            : [];

        return view('livewire.activity.report', [
            'project' => $project,
            'projectShareholder' => $projectShareholder,
            'results' => $project->results,
            'workers' => $this->workerOptions(),
            'previousSubmissions' => $projectShareholder->submissions()->with('creator')->get(),
            'showForm' => $this->editingId !== null || $this->selectedWorkerId !== null,
            'editingFiles' => $editingFiles,
        ]);
    }

    /**
     * Reset the form back to a blank "new report" state, keeping the selected
     * worker so the admin can quickly enter another.
     */
    private function resetForm(): void
    {
        $this->reset(['resultId', 'contacts', 'privacyConsent', 'consentFiles', 'note', 'attachments', 'editingId', 'existingAttachments']);
        $this->date = now()->toDateString();
        $this->resetValidation();
    }

    /**
     * All worker users, for the attribution dropdown.
     *
     * @return Collection<int, User>
     */
    private function workerOptions(): Collection
    {
        return User::query()
            ->where('role', UserRole::Worker->value)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function shareholder(): ProjectShareholder
    {
        return ProjectShareholder::findOrFail($this->projectShareholderId);
    }
}
