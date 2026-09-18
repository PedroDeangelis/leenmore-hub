<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A bearer token for the read-only REST API. See the migrations for the storage
 * rationale: authentication looks tokens up by SHA-256 hash, while the plaintext
 * is kept encrypted so the management page can reveal it on demand. Tokens from
 * before the encrypted column existed have a null `token` and cannot be re-shown.
 */
#[Fillable(['name', 'token_hash', 'token', 'last_four', 'expires_at', 'revoked_at', 'created_by', 'assigned_to'])]
class ApiToken extends Model
{
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Mint a token: persist its hash (for auth lookups) and its encrypted
     * plaintext (so admins can view it again from the list), and return the row
     * paired with the plaintext for the immediate reveal.
     *
     * @return array{token: self, plainText: string}
     */
    public static function generate(string $name, ?int $createdBy = null, ?\DateTimeInterface $expiresAt = null, ?int $assignedTo = null): array
    {
        // Prefixed so a leaked token is greppable and obviously ours.
        $plainText = 'lmh_'.Str::random(48);

        $token = self::create([
            'name' => $name,
            'token_hash' => hash('sha256', $plainText),
            'token' => $plainText,
            'last_four' => Str::substr($plainText, -4),
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
            'assigned_to' => $assignedTo,
        ]);

        return ['token' => $token, 'plainText' => $plainText];
    }

    /**
     * Find the active token matching a presented plaintext, or null. Constant
     * work regardless of outcome — the lookup is by hash, not a scan.
     */
    public static function findActiveByPlainText(string $plainText): ?self
    {
        return self::query()
            ->active()
            ->where('token_hash', hash('sha256', $plainText))
            ->first();
    }

    /**
     * Not revoked, and not past its expiry.
     *
     * @param  Builder<ApiToken>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Human status for the management list.
     */
    public function status(): string
    {
        return match (true) {
            $this->isRevoked() => 'revoked',
            $this->isExpired() => 'expired',
            default => 'active',
        };
    }

    /**
     * The admin who minted the token (may be null if that account was deleted).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The portal account this key belongs to, if any. Null is the common case —
     * a key issued to an outside consumer who has no login here.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Narrow to the tokens a given user may see and test with: everything for an
     * admin, only their own assigned keys for anyone else.
     *
     * This is the data-exposure boundary behind the docs tester — a viewer must
     * never be handed another client's key.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            $query->where('assigned_to', $user->id);
        }
    }
}
