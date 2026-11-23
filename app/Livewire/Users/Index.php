<?php

namespace App\Livewire\Users;

use App\Enums\UserRole;
use App\Http\Middleware\SetUserLocale;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The portal's login accounts. Reaching this page at all already requires
 * `manage-users`, so everything here — including minting a new account — is
 * admin-only; there is no self-service sign-up.
 */
#[Title('Users')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public bool $showCreate = false;

    public string $name = '';

    public string $email = '';

    public string $role = UserRole::Viewer->value;

    public string $locale = 'ko';

    public string $password = '';

    /**
     * Reset to the first page whenever the search term changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Create a login account.
     *
     * The admin sets the initial password directly — there is no invitation
     * email — and hands it to the person, who can have it changed from their
     * profile page.
     */
    public function create(): void
    {
        $this->authorize('manage-users');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'locale' => ['required', 'string', Rule::in(SetUserLocale::SUPPORTED)],
            'password' => ['required', 'string', Password::default()],
        ]);

        User::create($validated);

        $this->reset('name', 'email', 'role', 'locale', 'password', 'showCreate');
        $this->resetPage();

        $this->dispatch('toast', message: __('User created.'), variant: 'success');
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
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(15);
    }
}
