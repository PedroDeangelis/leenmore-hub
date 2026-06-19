<?php

namespace App\Livewire\Worker;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The field worker's home: the published projects they're assigned to, each with
 * a count of the shareholders assigned to them.
 */
#[Layout('components.layouts.worker')]
#[Title('Your projects')]
class Home extends Component
{
    public function render(): View
    {
        return view('livewire.worker.home', [
            'projects' => $this->projects(),
        ]);
    }

    /**
     * @return Collection<int, Project>
     */
    private function projects(): Collection
    {
        $workerId = auth()->id();

        return Project::query()
            ->where('status', ProjectStatus::Publish->value)
            ->whereHas('shareholders.workers', fn (Builder $q) => $q->whereKey($workerId))
            ->withCount(['shareholders as my_shareholders_count' => fn (Builder $q) => $q->whereHas('workers', fn (Builder $w) => $w->whereKey($workerId))])
            ->orderByDesc('end_date')
            ->get();
    }
}
