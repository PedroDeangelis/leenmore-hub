<?php

namespace App\Livewire\Projects;

use App\Enums\ResultColor;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectResult;
use App\Models\ProjectShareholder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Project')]
class Show extends Component
{
    #[Locked]
    public int $projectId;

    /** Which field is currently being edited inline: title|message|esignon|dates|null. */
    public ?string $editing = null;

    // Inline-edit working values.
    public string $title = '';

    public ?string $message = '';

    public ?string $link_manage_id = '';

    public ?string $start_date = null;

    public ?string $end_date = null;

    public ?int $shares_issued = null;

    public ?int $shares_target = null;

    // Results (판단) management.
    public bool $sorting = false;

    public bool $managingResults = false;

    /** @var array<int, array<string, mixed>> editable result rows in the modal */
    public array $rows = [];

    /** Stable keys for new/existing modal rows. */
    public int $rowSeq = 0;

    /** Roster search — matches name, registration, or date-of-birth code. */
    public string $shareholderSearch = '';

    /** Manage-workers modal state. */
    public bool $managingWorkers = false;

    public ?int $managingWorkersFor = null;

    /** Search term for the add-worker picker. */
    public string $workerSearch = '';

    /**
     * Mount from the route-bound project.
     */
    public function mount(Project $project): void
    {
        $this->projectId = $project->id;
        $this->syncFromProject($project);
    }

    /**
     * Begin editing a single field. Admins only.
     */
    public function edit(string $field): void
    {
        Gate::authorize('manage-projects');

        $this->resetValidation();
        $this->syncFromProject($this->project());
        $this->editing = $field;
    }

    /**
     * Save the field currently being edited.
     */
    public function save(): void
    {
        Gate::authorize('manage-projects');

        $rules = match ($this->editing) {
            'title' => ['title' => ['required', 'string', 'max:255']],
            'message' => ['message' => ['nullable', 'string', 'max:65535']],
            'esignon' => ['link_manage_id' => ['nullable', 'string', 'max:255']],
            'dates' => [
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date', $this->start_date ? 'after_or_equal:start_date' : ''],
            ],
            'shares' => [
                'shares_issued' => ['nullable', 'integer', 'min:0'],
                'shares_target' => ['nullable', 'integer', 'min:0'],
            ],
            default => [],
        };

        if ($rules === []) {
            $this->editing = null;

            return;
        }

        $validated = $this->validate($rules);
        $project = $this->project();

        match ($this->editing) {
            'title' => $project->title = $validated['title'],
            'message' => $project->message = $validated['message'] ?: null,
            'esignon' => $project->link_manage_id = $validated['link_manage_id'] ?: null,
            'dates' => $this->applyDates($project, $validated),
            'shares' => $this->applyShares($project, $validated),
            default => null,
        };

        $project->save();

        $this->editing = null;
        $this->dispatch('toast', message: __('Project updated.'), variant: 'success');
    }

    /**
     * Discard the in-progress edit.
     */
    public function cancelEdit(): void
    {
        $this->editing = null;
        $this->resetValidation();
    }

    /**
     * Publish the project (draft → publish), making it visible to assigned
     * workers. Stays on the page so the new status is reflected in place.
     * Admins only.
     */
    public function publish(): void
    {
        Gate::authorize('manage-projects');

        $this->project()->publish();

        $this->dispatch('toast', message: __('Project published.'), variant: 'success');
    }

    /**
     * Revert a published project back to draft (publish → draft), hiding it
     * from workers again. Stays on the page. Admins only.
     */
    public function revertToDraft(): void
    {
        Gate::authorize('manage-projects');

        $this->project()->restoreToDraft();

        $this->dispatch('toast', message: __('Project reverted to draft.'), variant: 'success');
    }

    /**
     * Archive the project (hidden from workers, still restorable) and return to
     * the list. Admins only.
     */
    public function archive(): mixed
    {
        Gate::authorize('manage-projects');

        $this->project()->archive();

        session()->flash('toast', ['message' => __('Project archived.'), 'variant' => 'success']);

        return $this->redirectRoute('projects.index', navigate: true);
    }

    /**
     * Permanently delete the project: it stays in the database but disappears
     * from the app and cannot be restored through the UI. Admins only.
     */
    public function delete(): mixed
    {
        Gate::authorize('manage-projects');

        $this->project()->markDeleted();

        session()->flash('toast', ['message' => __('Project deleted.'), 'variant' => 'success']);

        return $this->redirectRoute('projects.index', navigate: true);
    }

    /**
     * Toggle drag-to-reorder mode on the 판단 panel. Admins only.
     */
    public function toggleSort(): void
    {
        Gate::authorize('manage-projects');

        $this->sorting = ! $this->sorting;
    }

    /**
     * Persist a new result order after a drag. Admins only.
     */
    public function reorder(int $item, int $position): void
    {
        Gate::authorize('manage-projects');

        $ids = $this->project()->results()->pluck('id')->all();
        $ids = array_values(array_filter($ids, fn (int $id): bool => $id !== $item));
        array_splice($ids, $position, 0, [$item]);

        foreach ($ids as $order => $id) {
            ProjectResult::whereKey($id)->update(['sort_order' => $order]);
        }
    }

    /**
     * Open the manage-results modal, loading the current rows. Admins only.
     */
    public function manageResults(): void
    {
        Gate::authorize('manage-projects');

        $this->rowSeq = 0;
        $this->rows = $this->project()->results()->orderBy('sort_order')->get()
            ->map(fn (ProjectResult $r): array => [
                '_uid' => ++$this->rowSeq,
                'id' => $r->id,
                'name' => $r->name,
                'color' => $r->color->value,
                'contact_required' => $r->contact_required,
                'attachment_required' => $r->attachment_required,
            ])
            ->all();

        $this->sorting = false;
        $this->resetValidation();
        $this->managingResults = true;
    }

    /**
     * Append a blank result row in the modal.
     */
    public function addResultRow(): void
    {
        Gate::authorize('manage-projects');

        $this->rows[] = [
            '_uid' => ++$this->rowSeq,
            'id' => null,
            'name' => '',
            'color' => ResultColor::Gray->value,
            'contact_required' => false,
            'attachment_required' => false,
        ];
    }

    /**
     * Remove a result row from the modal (committed on save).
     */
    public function removeResultRow(int $index): void
    {
        Gate::authorize('manage-projects');

        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    /**
     * Persist the modal rows: update kept results, create new ones, delete the
     * rest, and renumber positions by row order. Admins only.
     */
    public function saveResults(): void
    {
        Gate::authorize('manage-projects');

        $this->validate([
            'rows' => ['array'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.color' => ['required', Rule::enum(ResultColor::class)],
            'rows.*.contact_required' => ['boolean'],
            'rows.*.attachment_required' => ['boolean'],
        ], attributes: collect($this->rows)
            ->flatMap(fn ($row, $i): array => ['rows.'.$i.'.name' => __('Result name')])
            ->all());

        $project = $this->project();
        $keptIds = [];

        foreach ($this->rows as $order => $row) {
            $attributes = [
                'name' => $row['name'],
                'color' => $row['color'],
                'contact_required' => (bool) ($row['contact_required'] ?? false),
                'attachment_required' => (bool) ($row['attachment_required'] ?? false),
                'sort_order' => $order,
            ];

            if (! empty($row['id'])) {
                $project->results()->whereKey($row['id'])->update($attributes);
                $keptIds[] = (int) $row['id'];
            } else {
                $keptIds[] = $project->results()->create($attributes)->id;
            }
        }

        $project->results()->whereNotIn('id', $keptIds)->delete();

        $this->managingResults = false;
        $this->dispatch('toast', message: __('Results saved.'), variant: 'success');
    }

    /**
     * Re-render after the embedded import component loads a roster.
     */
    #[On('shareholders-imported')]
    public function refreshShareholders(): void
    {
        // Empty: the dispatch triggers a re-render of the roster preview below.
    }

    /**
     * Open the manage-workers modal for one roster row. Admins + office.
     */
    public function manageWorkers(int $assignment): void
    {
        Gate::authorize('manage-shareholders');

        // Must belong to this project.
        $this->project()->shareholders()->findOrFail($assignment);

        $this->managingWorkersFor = $assignment;
        $this->workerSearch = '';
        $this->managingWorkers = true;
    }

    /**
     * Assign a worker user to the row being managed.
     */
    public function addWorker(int $worker): void
    {
        Gate::authorize('manage-shareholders');

        // Only actual workers may be assigned.
        User::query()->where('role', UserRole::Worker->value)->findOrFail($worker);

        $this->currentAssignment()->workers()->syncWithoutDetaching([$worker]);
        $this->workerSearch = '';
    }

    /**
     * Unassign a worker from the row being managed.
     */
    public function removeWorker(int $worker): void
    {
        Gate::authorize('manage-shareholders');

        $this->currentAssignment()->workers()->detach($worker);
    }

    /**
     * Clear the modal selection when it closes (entangled to false).
     */
    public function updatedManagingWorkers(bool $value): void
    {
        if (! $value) {
            $this->reset('managingWorkersFor', 'workerSearch');
        }
    }

    public function render(): View
    {
        $project = $this->project()->load('results');

        return view('livewire.projects.show', [
            'project' => $project,
            'countdown' => $project->meetingCountdown(),
            // The 판단 결과 현황 (submission tallies) section renders an empty
            // state until submissions land (audit §5). The 판단 panel is real.
            'shareholders' => $this->rosterQuery($project)->take(8)->get(),
            'matchCount' => $this->rosterQuery($project)->count(),
            'shareholderCount' => $project->shareholders()->count(),
            'shareholderSearching' => trim($this->shareholderSearch) !== '',
            'managingAssignment' => $this->managingAssignment($project),
            'workerOptions' => $this->workerOptions(),
        ]);
    }

    /**
     * The assignment whose workers are being managed (with its workers loaded).
     */
    private function managingAssignment(Project $project): ?ProjectShareholder
    {
        if (! $this->managingWorkers || $this->managingWorkersFor === null) {
            return null;
        }

        return $project->shareholders()->with(['workers', 'shareholder'])->find($this->managingWorkersFor);
    }

    /**
     * Worker users matching the picker search, excluding the already-assigned.
     *
     * @return Collection<int, User>
     */
    private function workerOptions(): Collection
    {
        if (! $this->managingWorkers || $this->managingWorkersFor === null) {
            return collect();
        }

        $assignedIds = $this->currentAssignment()->workers()->pluck('users.id')->all();

        return User::query()
            ->where('role', UserRole::Worker->value)
            ->when($this->workerSearch !== '', fn (Builder $q) => $q->where('name', 'like', '%'.trim($this->workerSearch).'%'))
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email']);
    }

    private function currentAssignment(): ProjectShareholder
    {
        return $this->project()->shareholders()->findOrFail($this->managingWorkersFor);
    }

    /**
     * The project's roster, filtered by the search term (name / registration /
     * date-of-birth code / worker name). Returns a fresh query each call.
     *
     * @return HasMany<ProjectShareholder, Project>
     */
    private function rosterQuery(Project $project): HasMany
    {
        $search = trim($this->shareholderSearch);
        $query = $project->shareholders()->with('shareholder', 'result', 'workers');

        if ($search !== '') {
            $digits = preg_replace('/\D/', '', $search);
            // A digits-only term (optionally with -/space) searches the
            // registration + birth code; anything with letters searches the
            // shareholder's name or an assigned worker's name.
            $numeric = $digits !== '' && preg_replace('/[\d\s-]/u', '', $search) === '';

            if ($numeric) {
                $query->whereHas('shareholder', function (Builder $person) use ($digits): void {
                    $person->where('registration', 'like', '%'.$digits.'%')
                        ->orWhere('date_of_birth_code', 'like', '%'.$digits.'%');
                });
            } else {
                $query->where(function (Builder $row) use ($search): void {
                    $row->whereHas('shareholder', fn (Builder $person) => $person->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('workers', fn (Builder $worker) => $worker->where('name', 'like', '%'.$search.'%'));
                });
            }
        }

        return $query;
    }

    /**
     * Copy the editable attributes off the model into the working properties.
     */
    private function syncFromProject(Project $project): void
    {
        $this->title = $project->title;
        $this->message = $project->message;
        $this->link_manage_id = $project->link_manage_id;
        $this->start_date = $project->start_date?->toDateString();
        $this->end_date = $project->end_date?->toDateString();
        $this->shares_issued = $project->shares_issued;
        $this->shares_target = $project->shares_target;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyDates(Project $project, array $validated): void
    {
        $project->start_date = $validated['start_date'] ?: null;
        $project->end_date = $validated['end_date'] ?: null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyShares(Project $project, array $validated): void
    {
        $project->shares_issued = $validated['shares_issued'];
        $project->shares_target = $validated['shares_target'];
    }

    private function project(): Project
    {
        return Project::findOrFail($this->projectId);
    }
}
