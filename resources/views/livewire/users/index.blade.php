<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900">{{ __('Users') }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ __('All registered users') }}</p>
        </div>

        <div class="flex w-full items-center gap-3 sm:w-auto">
            <div class="w-full sm:w-64">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" :placeholder="__('Search by name or email')"
                    aria-label="{{ __('Search users') }}" />
            </div>

            <x-ui.button wire:click="$set('showCreate', true)" class="shrink-0">
                <x-heroicon-o-plus class="size-4" />
                {{ __('Create user') }}
            </x-ui.button>
        </div>
    </div>

    <x-ui.table>
        <x-slot:head>
            <x-ui.table.heading>{{ __('Name') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Email') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Role') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Status') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Action') }}</x-ui.table.heading>
        </x-slot:head>

        @forelse ($users as $user)
            <tr wire:key="user-{{ $user->id }}" class="hover:bg-zinc-50">
                <x-ui.table.cell>
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-xs font-semibold text-white">
                            {{ $user->initials() }}
                        </span>
                        <a href="{{ route('users.show', $user) }}" wire:navigate x-on:click.stop
                            class="font-medium text-zinc-800 hover:text-primary hover:underline">{{ $user->name }}</a>
                    </div>
                </x-ui.table.cell>
                <x-ui.table.cell class="text-zinc-600">{{ $user->email }}</x-ui.table.cell>
                <x-ui.table.cell>
                    <span
                        class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                        {{ $user->role->label() }}
                    </span>
                </x-ui.table.cell>
                <x-ui.table.cell>
                    @if ($user->deactivated_at)
                        <span
                            class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-600">{{ __('Inactive') }}</span>
                    @else
                        <span
                            class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">{{ __('Active') }}</span>
                    @endif
                </x-ui.table.cell>
                <x-ui.table.cell>
                    <a href="{{ route('users.show', $user) }}" wire:navigate
                        class="text-primary hover:underline">{{ __('View') }}</a>
                </x-ui.table.cell>
            </tr>
        @empty
            <x-ui.table.empty :cols="6">{{ __('No users found.') }}</x-ui.table.empty>
        @endforelse
    </x-ui.table>

    @if ($users->hasPages())
        <div>
            {{ $users->links() }}
        </div>
    @endif

    {{-- New login account. Admin-only, like the rest of this page. --}}
    <x-ui.modal model="showCreate">
        <form wire:submit="create" class="space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900">{{ __('Create user') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ __('Set an initial password and hand it to the person — there is no invitation email.') }}</p>
            </div>

            <x-ui.input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="off" />

            <x-ui.input wire:model="email" :label="__('Email (login)')" type="email" required autocomplete="off" />

            <div class="grid grid-cols-2 gap-5">
                <x-ui.select wire:model="role" :label="__('Role')">
                    @foreach (\App\Enums\UserRole::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select wire:model="locale" :label="__('Language')">
                    <option value="ko">{{ __('Korean') }}</option>
                    <option value="en">{{ __('English') }}</option>
                </x-ui.select>
            </div>

            <x-ui.input wire:model="password" :label="__('Password')" type="password" required autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable />

            <div class="flex justify-end gap-3">
                <x-ui.button type="button" variant="outline" wire:click="$set('showCreate', false)">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button type="submit">
                    <span wire:loading.remove wire:target="create">{{ __('Create user') }}</span>
                    <span wire:loading wire:target="create">{{ __('Creating…') }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</section>
