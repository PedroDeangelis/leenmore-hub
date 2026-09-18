<?php

namespace App\Livewire\Api;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The API tab's landing page: mint, label, revoke and delete the bearer tokens
 * that authenticate the REST API. A token's plaintext can be viewed at any time
 * by clicking it in the list (it is stored encrypted); only tokens minted before
 * the encrypted column existed are unrecoverable.
 *
 * Viewers reach this page too, but only to collect a key already assigned to
 * them: the list is scoped by {@see ApiToken::scopeVisibleTo()} and every
 * mutating action is gated on `manage-api-tokens`, so a token is never exposed
 * to, or destroyed by, someone it was not issued to.
 */
#[Title('API access')]
class Tokens extends Component
{
    use WithPagination;

    /** Who the token is for — the label shown in the list. */
    #[Validate('required|string|max:100')]
    public string $name = '';

    /** Optional expiry (YYYY-MM-DD); blank means the token never expires. */
    #[Validate('nullable|date|after:today')]
    public string $expires_at = '';

    /**
     * Optional portal account the key belongs to. Blank — the common case — is a
     * key for an outside consumer with no login here.
     */
    #[Validate('nullable|integer|exists:users,id')]
    public string $assigned_to = '';

    /** The plaintext to display right after creation; cleared on dismiss. */
    public ?string $plainText = null;

    /** ID of the row whose full token is currently shown in the list, if any. */
    public ?int $revealedId = null;

    public bool $showCreate = false;

    public function create(): void
    {
        $this->authorize('manage-api-tokens');

        $validated = $this->validate();

        ['plainText' => $plainText] = ApiToken::generate(
            name: $validated['name'],
            createdBy: auth()->id(),
            expiresAt: $validated['expires_at'] !== '' ? Carbon::parse($validated['expires_at'])->endOfDay() : null,
            assignedTo: $validated['assigned_to'] !== '' ? (int) $validated['assigned_to'] : null,
        );

        $this->plainText = $plainText;
        $this->reset('name', 'expires_at', 'assigned_to', 'showCreate');

        $this->dispatch('toast', message: __('Token created.'), variant: 'success');
    }

    /**
     * Toggle the full-token display for one row. Clicking the same row hides it
     * again; clicking another row moves the reveal there.
     */
    public function reveal(int $tokenId): void
    {
        // Only within what this user may see; a tampered id reveals nothing.
        if (! $this->visibleTokenIds()->contains($tokenId)) {
            return;
        }

        $this->revealedId = $this->revealedId === $tokenId ? null : $tokenId;
    }

    /**
     * Block a token: it stays in the list for the audit trail but stops working
     * immediately.
     */
    public function revoke(int $tokenId): void
    {
        $this->authorize('manage-api-tokens');

        ApiToken::whereKey($tokenId)->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $this->dispatch('toast', message: __('Token blocked.'), variant: 'success');
    }

    /**
     * Remove a token entirely.
     */
    public function delete(int $tokenId): void
    {
        $this->authorize('manage-api-tokens');

        ApiToken::whereKey($tokenId)->delete();

        $this->dispatch('toast', message: __('Token deleted.'), variant: 'success');
    }

    public function dismissPlainText(): void
    {
        $this->plainText = null;
    }

    public function render(): View
    {
        return view('livewire.api.tokens', [
            'tokens' => $this->tokens(),
            'assignableUsers' => $this->assignableUsers(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, ApiToken>
     */
    private function tokens(): LengthAwarePaginator
    {
        return ApiToken::query()
            ->with(['creator:id,name', 'assignee:id,name'])
            ->visibleTo(auth()->user())
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * Ids of the tokens this user may see — the guard behind reveal().
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function visibleTokenIds(): \Illuminate\Support\Collection
    {
        return ApiToken::query()
            ->visibleTo(auth()->user())
            ->pluck('id');
    }

    /**
     * Accounts a new key can be assigned to. Admins already see every key, so
     * assignment only meaningfully narrows things for viewers.
     *
     * @return Collection<int, User>
     */
    private function assignableUsers(): Collection
    {
        return User::query()
            ->whereNull('deactivated_at')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
