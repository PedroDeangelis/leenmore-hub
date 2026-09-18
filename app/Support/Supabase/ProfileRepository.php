<?php

namespace App\Support\Supabase;

use Illuminate\Support\Facades\Cache;

/**
 * Supabase `profiles` — the 활동가 (field workers) and admins of the client's own
 * site. They never log in here; this app's `users` table is a separate, much
 * smaller list of portal accounts.
 *
 * This repository backs no endpoint of its own. It exists because the two halves
 * of Supabase disagree about how to name a worker: `submission.user_id` and
 * `receipt.user_id` are real uuids pointing here, while `shareholder.user` is a
 * text[] of worker *names*. Filtering a roster by worker therefore means
 * translating a uuid into a name first.
 */
class ProfileRepository
{
    private const TABLE = 'profiles';

    public function __construct(private readonly SupabaseClient $client) {}

    /**
     * The display name for a worker uuid, or null if there is no such profile.
     *
     * Names are how `shareholder.user` identifies people, so this is the bridge
     * between the two schemes. In practice the whole Korean name sits in
     * `first_name` — no profile in the live data has a `last_name` — but both
     * are joined so a later-populated surname does not silently break matching.
     */
    public function nameFor(string $uuid): ?string
    {
        $profile = Cache::remember(
            'supabase.profile.'.$uuid,
            (int) config('leenmore.supabase.cache_ttl', 120) ?: 60,
            fn (): ?array => $this->client->first(self::TABLE, [
                'select' => 'id,first_name,last_name',
                'id' => 'eq.'.$uuid,
            ]),
        );

        if ($profile === null) {
            return null;
        }

        $name = trim(($profile['last_name'] ?? '').($profile['first_name'] ?? ''));

        return $name !== '' ? $name : null;
    }
}
