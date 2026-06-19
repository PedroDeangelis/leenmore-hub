<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900">{{ __('Users') }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ __('All registered users') }}</p>
        </div>

        <div class="w-full sm:w-64">
            <x-ui.input wire:model.live.debounce.300ms="search" type="search" :placeholder="__('Search by name, email or phone')"
                aria-label="{{ __('Search users') }}" />
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200">
        <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50">
                <tr class="text-start text-xs font-medium uppercase tracking-wide text-zinc-500">
                    <th class="px-4 py-3 text-start font-medium">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Email') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Role') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Phone') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 bg-white">
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}" class=" hover:bg-zinc-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-xs font-semibold text-white">
                                    {{ $user->initials() }}
                                </span>
                                <a href="{{ route('users.show', $user) }}" wire:navigate x-on:click.stop
                                    class="font-medium text-zinc-800 hover:text-primary hover:underline">{{ $user->name }}</a>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-zinc-600">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                                {{ __(ucfirst($user->role->value)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-zinc-600">{{ $user->phone ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($user->deactivated_at)
                                <span
                                    class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-600">{{ __('Inactive') }}</span>
                            @else
                                <span
                                    class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">{{ __('Active') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('users.show', $user) }}" wire:navigate
                                class="text-primary hover:underline">{{ __('View') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-zinc-500">{{ __('No users found.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($users->hasPages())
        <div>
            {{ $users->links() }}
        </div>
    @endif
</section>
