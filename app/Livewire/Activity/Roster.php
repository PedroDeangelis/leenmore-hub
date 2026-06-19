<?php

namespace App\Livewire\Activity;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectShareholder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Activity reports — step 2: the project's shareholder roster, searchable and
 * filterable, paginated. Picking a row opens the manual report page.
 * Admin + office (gated by `edit-submissions`).
 */
#[Title('Activity reports')]
class Roster extends Component
{
    use WithPagination;

    #[Locked]
    public int $projectId;

    #[Url(as: 'q')]
    public string $search = '';

    /** Reports filter: '' = all, 'has' = with reports, 'none' = without. */
    #[Url]
    public string $reports = '';

    public function mount(Project $project): void
    {
        abort_unless($project->status === ProjectStatus::Publish, 404);

        $this->projectId = $project->id;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedReports(): void
    {
        $this->resetPage();
    }

    /**
     * The roster rows: assignments with their person, current 판단, and report
     * count — filtered by the search term and the reports filter.
     *
     * @return LengthAwarePaginator<int, ProjectShareholder>
     */
    private function rows(Project $project): LengthAwarePaginator
    {
        $search = trim($this->search);

        return $project->shareholders()
            ->with(['shareholder', 'result'])
            ->withCount('submissions')
            ->when($this->reports === 'has', fn (Builder $q) => $q->whereHas('submissions'))
            ->when($this->reports === 'none', fn (Builder $q) => $q->whereDoesntHave('submissions'))
            ->when($search !== '', function (Builder $q) use ($search): void {
                $digits = preg_replace('/\D/', '', $search);
                // A digits-only term matches the registration / birth code; a term
                // with letters matches the shareholder's name.
                $numeric = $digits !== '' && preg_replace('/[\d\s-]/u', '', $search) === '';

                $q->whereHas('shareholder', function (Builder $person) use ($search, $digits, $numeric): void {
                    if ($numeric) {
                        $person->where('registration', 'like', '%'.$digits.'%')
                            ->orWhere('date_of_birth_code', 'like', '%'.$digits.'%');
                    } else {
                        $person->where('name', 'like', '%'.$search.'%');
                    }
                });
            })
            ->paginate(20);
    }

    public function render(): View
    {
        $project = Project::findOrFail($this->projectId);

        return view('livewire.activity.roster', [
            'project' => $project,
            'rows' => $this->rows($project),
        ]);
    }
}
