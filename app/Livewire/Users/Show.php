<?php

namespace App\Livewire\Users;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('User profile')]
class Show extends Component
{
    use ProfileValidationRules;

    #[Locked]
    public int $userId;

    public string $name = '';

    public string $email = '';

    public ?string $email_receiver = '';

    public ?string $phone = '';

    public string $role = '';

    public string $locale = 'ko';

    public string $password = '';

    /**
     * Mount the component from the route-bound user.
     */
    public function mount(User $user): void
    {
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->email_receiver = $user->email_receiver;
        $this->phone = $user->phone;
        $this->role = $user->role->value;
        $this->locale = $user->locale ?? config('app.locale');
    }

    /**
     * Update the editable profile fields.
     *
     * The login email is intentionally read-only, so it is never validated or
     * saved. `email_receiver` is the optional address notifications are sent to.
     */
    public function updateProfile(): void
    {
        $validated = $this->validate([
            'name' => $this->nameRules(),
            'email_receiver' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'locale' => ['required', 'string', 'in:ko,en'],
        ]);

        // Guard against an admin locking themselves out by self-demotion.
        if ($this->isSelf() && $validated['role'] !== UserRole::Admin->value) {
            $this->addError('role', __('You cannot change your own role.'));

            return;
        }

        $validated['email_receiver'] = $validated['email_receiver'] ?: null;
        $validated['phone'] = $validated['phone'] ?: null;

        $this->user()->fill($validated)->save();

        $this->dispatch('toast', message: __('User updated.'), variant: 'success');
    }

    /**
     * Set a new password for the user.
     *
     * Admins reset passwords directly, so no current password is required.
     */
    public function updatePassword(): void
    {
        $validated = $this->validate([
            'password' => ['required', 'string', Password::default()],
        ]);

        $this->user()->update(['password' => $validated['password']]);

        $this->reset('password');

        $this->dispatch('toast', message: __('Password updated.'), variant: 'success');
    }

    /**
     * Toggle whether the user is deactivated.
     */
    public function toggleActivation(): void
    {
        if ($this->isSelf()) {
            $this->dispatch('toast', message: __('You cannot deactivate your own account.'), variant: 'error');

            return;
        }

        $user = $this->user();
        $user->deactivated_at = $user->deactivated_at ? null : now();
        $user->save();

        $this->dispatch('toast', message: $user->deactivated_at
            ? __('User deactivated.')
            : __('User reactivated.'), variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.users.show', [
            'user' => $this->user(),
        ]);
    }

    /**
     * Whether the user being edited is the authenticated admin.
     */
    #[Computed]
    public function isSelf(): bool
    {
        return $this->userId === auth()->id();
    }

    private function user(): User
    {
        return User::findOrFail($this->userId);
    }
}
