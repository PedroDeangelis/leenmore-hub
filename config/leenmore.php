<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supabase (the system of record)
    |--------------------------------------------------------------------------
    |
    | The client's own website owns this data; we only ever read it, over
    | PostgREST. `key` may be the anon key where row-level security allows these
    | reads — prefer that to the service-role key, which bypasses RLS entirely.
    |
    */

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'key' => env('SUPABASE_KEY'),
        'timeout' => (int) env('SUPABASE_TIMEOUT', 15),

        // Small, hot and rarely changed: the project list and the judgment
        // tally. Seconds; 0 disables caching.
        'cache_ttl' => (int) env('SUPABASE_CACHE_TTL', 120),
    ],

];
