<?php

namespace App\Livewire\Worker;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectShareholder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A field worker's view of one project: only the shareholders assigned to them,
 * with search and field actions (copy address / open in maps).
 */
#[Layout('components.layouts.worker')]
#[Title('Shareholders')]
class ProjectShow extends Component
{
    #[Locked]
    public int $projectId;

    #[Url]
    public string $search = '';

    /**
     * Bind to a published project the worker is actually assigned to; 404 otherwise.
     */
    public function mount(Project $project): void
    {
        abort_unless($project->status === ProjectStatus::Publish, 404);
        abort_unless($this->assignedToWorker($project)->exists(), 404);

        $this->projectId = $project->id;
    }

    public function render(): View
    {
        $project = Project::findOrFail($this->projectId);

        return view('livewire.worker.project-show', [
            'project' => $project,
            'shareholders' => $this->shareholders($project),
        ]);
    }

    /**
     * @return Collection<int, ProjectShareholder>
     */
    private function shareholders(Project $project): Collection
    {
        $search = trim($this->search);

        return $this->assignedToWorker($project)
            ->with(['shareholder', 'result'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $digits = preg_replace('/\D/', '', $search);

                $query->whereHas('shareholder', function (Builder $person) use ($search, $digits): void {
                    $person->where('name', 'like', '%'.$search.'%');

                    if ($digits !== '') {
                        $person->orWhere('registration', 'like', '%'.$digits.'%')
                            ->orWhere('date_of_birth_code', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->orderBy('no')
            ->get();
    }

    /**
     * The project's roster restricted to the rows assigned to the current worker.
     *
     * @return Builder<ProjectShareholder>
     */
    private function assignedToWorker(Project $project): Builder
    {
        return ProjectShareholder::query()
            ->where('project_id', $project->id)
            ->whereHas('workers', fn (Builder $q) => $q->whereKey(auth()->id()));
    }
}
