<?php

namespace App\Livewire\Worker;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One project's resource room for the worker — its links and downloadable files.
 * Bound only to a published project the worker is assigned to.
 */
#[Layout('components.layouts.worker')]
#[Title('Project resources')]
class ResourceShow extends Component
{
    #[Locked]
    public int $projectId;

    public string $search = '';

    public function mount(Project $project): void
    {
        abort_unless($project->status === ProjectStatus::Publish, 404);
        abort_unless(
            $project->shareholders()->whereHas('workers', fn (Builder $q) => $q->whereKey(auth()->id()))->exists(),
            404,
        );

        $this->projectId = $project->id;
    }

    public function render(): View
    {
        $project = Project::findOrFail($this->projectId);
        $search = trim($this->search);

        return view('livewire.worker.resource-show', [
            'project' => $project,
            'resources' => $this->resources($project, $search),
        ]);
    }

    /**
     * @return Collection<int, ProjectResource>
     */
    private function resources(Project $project, string $search): Collection
    {
        return $project->resources()
            ->when($search !== '', fn (Builder $q) => $q->where('title', 'like', '%'.$search.'%'))
            ->get();
    }
}
