{{--
    OAuth consent screen, shown when an AI client (Claude, ChatGPT, Gemini) connects to the
    MCP server in routes/ai.php. Passport renders it via Passport::authorizationView() in
    AppServiceProvider. Deliberately built on the app's own auth layout so it is visibly the
    hub and not a look-alike page asking for your credentials.
--}}
<x-layouts::auth.simple :title="__('Connect an AI assistant')">
    <div class="flex w-full flex-col items-center text-center">
        <div class="rounded-full bg-primary/10 p-3">
            <x-heroicon-o-shield-check class="size-8 text-primary" />
        </div>

        <h1 class="mt-4 text-xl font-semibold text-zinc-900">
            {{ __('Connect :client?', ['client' => $client->name]) }}
        </h1>

        <p class="mt-1 text-sm text-zinc-500">
            {{ __('This will let :client read Leenmore Hub data on your behalf.', ['client' => $client->name]) }}
        </p>
    </div>

    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
        <p class="text-xs text-zinc-500">{{ __('Signed in as') }}</p>
        <p class="mt-0.5 text-sm font-medium text-zinc-900">{{ $user->email }}</p>
    </div>

    <ul class="flex flex-col gap-3 text-sm">
        <li class="flex items-start gap-2.5">
            <x-heroicon-o-eye class="mt-0.5 size-4 shrink-0 text-zinc-400" />
            <span class="text-zinc-600">
                {{ __('Read projects, shareholder rosters, activity reports, receipts and staff.') }}
            </span>
        </li>
        <li class="flex items-start gap-2.5">
            <x-heroicon-o-lock-closed class="mt-0.5 size-4 shrink-0 text-zinc-400" />
            <span class="text-zinc-600">
                {{ __('Nothing can be created, changed or deleted.') }}
            </span>
        </li>
        <li class="flex items-start gap-2.5">
            <x-heroicon-o-shield-exclamation class="mt-0.5 size-4 shrink-0 text-zinc-400" />
            <span class="text-zinc-600">
                {{ __('실명번호 is always masked; contact numbers and full addresses are never sent.') }}
            </span>
        </li>
    </ul>

    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
        <p class="text-xs text-amber-800">
            {{ __('Data you ask about is sent to this provider and may be retained by them. Only connect assistants you trust.') }}
        </p>
    </div>

    <div class="flex items-center gap-3">
        {{-- Only `auth_token` is read back: the pending request itself is pulled from the
             session by RetrievesAuthRequestFromSession, so no client/state fields are needed. --}}
        <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
            @csrf
            @method('DELETE')
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <x-ui.button type="submit" variant="outline" class="w-full">
                {{ __('Cancel') }}
            </x-ui.button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
            @csrf
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <x-ui.button type="submit" class="w-full">
                {{ __('Connect') }}
            </x-ui.button>
        </form>
    </div>
</x-layouts::auth.simple>
