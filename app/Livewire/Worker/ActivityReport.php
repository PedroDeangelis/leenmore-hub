<?php

namespace App\Livewire\Worker;

use App\Enums\ProjectStatus;
use App\Livewire\Concerns\ActivityReportForm;
use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Submission;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The field worker's activity report (활동 보고) for one shareholder. Filing it
 * records a Submission and sets the shareholder's current 판단 result. The chosen
 * result's flags drive whether contact / an attachment is required. The form
 * state + validation + file handling live in {@see ActivityReportForm}.
 */
#[Layout('components.layouts.worker')]
#[Title('Activity report')]
class ActivityReport extends Component
{
    use ActivityReportForm;
    use WithFileUploads;

    #[Locked]
    public int $projectId;

    #[Locked]
    public int $projectShareholderId;

    /**
     * Bind to a published project + an assignment that belongs to it and to the
     * current worker; 404 otherwise.
     */
    public function mount(Project $project, ProjectShareholder $projectShareholder): void
    {
        abort_unless($project->status === ProjectStatus::Publish, 404);
        abort_unless($projectShareholder->project_id === $project->id, 404);
        abort_unless($projectShareholder->workers()->whereKey(auth()->id())->exists(), 404);

        $this->projectId = $project->id;
        $this->projectShareholderId = $projectShareholder->id;
        $this->date = now()->toDateString();
        // Contact fields start empty — the worker enters the number they reached.
    }

    /**
     * Validate, persist the report, and update the shareholder's current result.
     */
    public function save(): mixed
    {
        $project = Project::with('results')->findOrFail($this->projectId);
        $projectShareholder = ProjectShareholder::findOrFail($this->projectShareholderId);

        // The activity date is locked to today — never trust a client-sent value.
        $this->date = now()->toDateString();

        $this->validate($this->reportRules($project->results->pluck('id')->all()));

        $result = $project->results->firstWhere('id', $this->resultId);
        $contacts = $this->filledContacts();

        // The chosen 판단 decides whether contact / an attachment is mandatory.
        $this->requireContactAndAttachment($result, $contacts);

        [$paths, $consentPaths] = $this->storeFiles($projectShareholder->id);

        Submission::create([
            'project_id' => $project->id,
            'project_shareholder_id' => $projectShareholder->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->name,
            'date' => $this->date,
            'result' => $result->name,
            'contact' => $contacts->isNotEmpty() ? $contacts->implode(', ') : null,
            'privacy_consent' => $this->privacyConsent,
            'note' => $this->note ?: null,
            'files' => $paths !== [] ? $paths : null,
            'privacy_consent_files' => $consentPaths !== [] ? $consentPaths : null,
        ]);

        // Worker submissions own the current 판단 result (see ShareholderImporter).
        $projectShareholder->update([
            'result_id' => $result->id,
            'last_note' => $this->note ?: null,
        ]);

        session()->flash('toast', ['message' => __('Report submitted.'), 'variant' => 'success']);

        return $this->redirectRoute('worker.projects.show', $project, navigate: true);
    }

    public function render(): View
    {
        $project = Project::with('results')->findOrFail($this->projectId);
        $projectShareholder = ProjectShareholder::with(['shareholder', 'result'])->findOrFail($this->projectShareholderId);

        return view('livewire.worker.activity-report', [
            'project' => $project,
            'projectShareholder' => $projectShareholder,
            'results' => $project->results,
            'previousSubmissions' => Submission::query()
                ->where('project_shareholder_id', $projectShareholder->id)
                ->latest('date')
                ->latest('id')
                ->get(),
        ]);
    }
}
