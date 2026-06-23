<?php

namespace App\Livewire\Resources;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The resource-room admin landing page: every project, with its resource count.
 * Picking a project opens its manage page. Gated by `manage-resources`.
 */
#[Title('Project resources')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('manage-resources');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    private function projects(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Project::query()
            ->when($search !== '', fn (Builder $q) => $q->where('title', 'like', '%'.$search.'%'))
            ->withCount('resources')
            ->orderByDesc('end_date')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.resources.index', [
            'projects' => $this->projects(),
        ]);
    }
}
