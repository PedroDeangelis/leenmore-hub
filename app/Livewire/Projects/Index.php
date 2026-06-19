<?php

namespace App\Livewire\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Projects')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    /** '' = active (draft + publish), a 'draft'/'publish' value, or 'archived'. */
    #[Url]
    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Restore an archived project (back to draft). Admins only. Scoping the
     * lookup to archived means a deleted or active id 404s rather than restoring.
     */
    public function restore(int $project): void
    {
        Gate::authorize('manage-projects');

        Project::query()->archived()->findOrFail($project)->restoreToDraft();

        $this->dispatch('toast', message: __('Project restored.'), variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.projects.index', [
            'projects' => $this->projects(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    private function projects(): LengthAwarePaginator
    {
        $assignable = array_map(fn (ProjectStatus $s) => $s->value, ProjectStatus::assignable());

        return Project::query()
            ->when($this->status === '', fn (Builder $query) => $query->active())
            ->when($this->status === 'archived', fn (Builder $query) => $query->archived())
            ->when(in_array($this->status, $assignable, true), fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->search !== '', fn (Builder $query) => $query->where('title', 'like', '%'.$this->search.'%'))
            ->latest()
            ->paginate(15);
    }
}
