<?php

namespace App\Livewire\Api;

use App\Models\ApiToken;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    /** The plaintext to display right after creation; cleared on dismiss. */
    public ?string $plainText = null;

    /** ID of the row whose full token is currently shown in the list, if any. */
    public ?int $revealedId = null;

    public bool $showCreate = false;

    public function create(): void
    {
        $validated = $this->validate();

        ['plainText' => $plainText] = ApiToken::generate(
            name: $validated['name'],
            createdBy: auth()->id(),
            expiresAt: $validated['expires_at'] !== '' ? Carbon::parse($validated['expires_at'])->endOfDay() : null,
        );

        $this->plainText = $plainText;
        $this->reset('name', 'expires_at', 'showCreate');

        $this->dispatch('toast', message: __('Token created.'), variant: 'success');
    }

    /**
     * Toggle the full-token display for one row. Clicking the same row hides it
     * again; clicking another row moves the reveal there.
     */
    public function reveal(int $tokenId): void
    {
        $this->revealedId = $this->revealedId === $tokenId ? null : $tokenId;
    }

    /**
     * Block a token: it stays in the list for the audit trail but stops working
     * immediately.
     */
    public function revoke(int $tokenId): void
    {
        ApiToken::whereKey($tokenId)->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $this->dispatch('toast', message: __('Token blocked.'), variant: 'success');
    }

    /**
     * Remove a token entirely.
     */
    public function delete(int $tokenId): void
    {
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
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, ApiToken>
     */
    private function tokens(): LengthAwarePaginator
    {
        return ApiToken::query()
            ->with('creator:id,name')
            ->orderByDesc('id')
            ->paginate(15);
    }
}
