<?php

namespace App\Livewire\EmailToWorker;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The 활동가에게 이메일 보내기 landing page: pick the project whose workers get
 * the email. Active projects only, ordered like the legacy page — nearest
 * upcoming meeting (end date) first, then finished meetings newest-first,
 * projects without an end date last.
 */
#[Title('Email to worker')]
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
        $today = now()->toDateString();

        return Project::query()
            ->active()
            ->when($search !== '', fn (Builder $query) => $query->where('title', 'like', '%'.$search.'%'))
            // Legacy sort: upcoming end dates nearest-first, expired newest-first, no end date last.
            ->orderByRaw('CASE WHEN end_date IS NULL THEN 2 WHEN end_date >= ? THEN 0 ELSE 1 END', [$today])
            ->orderByRaw('CASE WHEN end_date >= ? THEN end_date END ASC', [$today])
            ->orderByRaw('CASE WHEN end_date < ? THEN end_date END DESC', [$today])
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.email-to-worker.index', [
            'projects' => $this->projects(),
        ]);
    }
}
