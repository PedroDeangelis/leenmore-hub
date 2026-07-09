<?php

namespace App\Livewire\ActivityData;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The 활동 데이터 landing page: pick the project whose worker-productivity
 * analytics to view. Active projects, newest first (legacy order).
 */
#[Title('Activity data')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

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
            ->active()
            ->when($search !== '', fn (Builder $query) => $query->where('title', 'like', '%'.$search.'%'))
            ->latest('id')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.activity-data.index', [
            'projects' => $this->projects(),
        ]);
    }
}
