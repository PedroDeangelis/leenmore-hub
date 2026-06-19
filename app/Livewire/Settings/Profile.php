<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profile settings')]
class Profile extends Component
{
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public string $locale = 'ko';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->locale = Auth::user()->locale ?? config('app.locale');
    }

    /**
     * Update the profile information for the currently authenticated user.
     *
     * The email address is intentionally not editable here, so it is excluded
     * from validation and is never saved.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => $this->nameRules(),
            'locale' => ['required', 'string', 'in:ko,en'],
        ]);

        $localeChanged = $user->locale !== $validated['locale'];

        $user->fill($validated);

        $user->save();

        if ($localeChanged) {
            // Reload so the whole page re-renders in the new language.
            $this->redirectRoute('profile.edit', navigate: true);

            return;
        }

        $this->dispatch('toast', message: __('Profile updated.'), variant: 'success');
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        $this->dispatch('toast', message: __('A new verification link has been sent to your email address.'));
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }
}
