<?php

namespace App\Livewire\Activity;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Activity reports — step 1: pick a published project to enter reports for.
 * Manual report entry mirrors the field-worker flow, so only published (actively
 * canvassed) projects are listed. Admin + office (gated by `edit-submissions`).
 */
#[Title('Activity reports')]
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
            ->where('status', ProjectStatus::Publish->value)
            ->when($search !== '', fn (Builder $q) => $q->where('title', 'like', '%'.$search.'%'))
            ->withCount('submissions')
            ->orderByRaw('end_date is null, end_date asc')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.activity.index', [
            'projects' => $this->projects(),
        ]);
    }
}
