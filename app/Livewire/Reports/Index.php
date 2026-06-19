<?php

namespace App\Livewire\Reports;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The reports archive (실시간 현황): every project that has at least one activity
 * report, most recent activity first. Picking a project opens its reports.
 * Read-only — admins + office (gated by `view-submissions`).
 */
#[Title('Reports')]
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
     * Projects that have reports, filtered by the search term, ordered by their
     * most recent report.
     *
     * @return LengthAwarePaginator<int, Project>
     */
    private function projects(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Project::query()
            ->whereHas('submissions')
            ->withMax('submissions as last_report_at', 'created_at')
            ->withCount('submissions')
            ->when($search !== '', fn (Builder $q) => $q->where('title', 'like', '%'.$search.'%'))
            ->orderByDesc('last_report_at')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.reports.index', [
            'projects' => $this->projects(),
        ]);
    }
}
