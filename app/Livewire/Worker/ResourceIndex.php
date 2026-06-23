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
 * The worker's resource room landing page (프로젝트 자료실): the published projects
 * they're assigned to that actually have resources.
 */
#[Layout('components.layouts.worker')]
#[Title('Project resources')]
class ResourceIndex extends Component
{
    public function render(): View
    {
        return view('livewire.worker.resource-index', [
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
            ->whereHas('resources')
            ->withCount('resources')
            ->orderByDesc('end_date')
            ->get();
    }
}
