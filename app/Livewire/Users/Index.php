<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Users')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    /**
     * Reset to the first page whenever the search term changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.users.index', [
            'users' => $this->users(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    private function users(): LengthAwarePaginator
    {
        return User::query()
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(15);
    }
}
