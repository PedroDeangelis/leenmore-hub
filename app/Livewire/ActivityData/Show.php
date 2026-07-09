<?php

namespace App\Livewire\ActivityData;

use App\Models\Project;
use App\Support\ActivityData;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The 활동 데이터 analytics page for one project: per-worker contact/proxy
 * statistics with a multi-day filter, sortable columns, summary cards, and
 * the Excel download. Port of the legacy SingleActivityData page; the math
 * lives in {@see ActivityData} so the workbook always matches the screen.
 */
#[Title('Activity data')]
class Show extends Component
{
    #[Locked]
    public int $projectId;

    /** @var list<string> selected activity days (Y-m-d); empty = all */
    public array $selectedDays = [];

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public function mount(Project $project): void
    {
        $this->projectId = $project->id;
    }

    /**
     * Toggle a header sort: same column flips direction, a new column starts
     * ascending (legacy ArrowSortFilter behaviour).
     */
    public function sort(string $column): void
    {
        $allowed = ['name', 'totalReports', 'sharesContacted', 'proxyShareholders', 'proxyShares'];

        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortBy = $column;
        $this->sortDirection = 'asc';
    }

    public function toggleDay(string $day): void
    {
        if (! in_array($day, $this->data->allDays(), true)) {
            return;
        }

        $this->selectedDays = in_array($day, $this->selectedDays, true)
            ? array_values(array_diff($this->selectedDays, [$day]))
            : [...$this->selectedDays, $day];

        unset($this->data);
    }

    /** The 전체보기 option: clear the day filter. */
    public function showAllDays(): void
    {
        $this->selectedDays = [];

        unset($this->data);
    }

    #[Computed]
    public function project(): Project
    {
        return Project::findOrFail($this->projectId);
    }

    #[Computed]
    public function data(): ActivityData
    {
        return ActivityData::for($this->project, $this->selectedDays);
    }

    /**
     * The per-worker rows in the chosen sort order.
     *
     * @return list<array{key: string, name: string, totalReports: int, sharesContacted: int,
     *     proxyShareholders: int, proxyShares: int, daysWorked: int, shareholderCount: int,
     *     assignedShares: int}>
     */
    #[Computed]
    public function rows(): array
    {
        $rows = $this->data->workers();

        usort($rows, function (array $a, array $b): int {
            $result = $this->sortBy === 'name'
                ? strcmp($a['name'], $b['name'])
                : $a[$this->sortBy] <=> $b[$this->sortBy];

            return $this->sortDirection === 'desc' ? -$result : $result;
        });

        return $rows;
    }

    public function render(): View
    {
        return view('livewire.activity-data.show');
    }
}
