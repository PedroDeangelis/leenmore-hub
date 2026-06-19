<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <nav aria-label="{{ __('Settings') }}" class="space-y-1">
            <a
                href="{{ route('profile.edit') }}"
                wire:navigate
                class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('profile.edit') ? 'bg-primary/10 text-primary' : 'text-zinc-600 hover:bg-zinc-100' }}"
            >{{ __('Profile') }}</a>
            <a
                href="{{ route('security.edit') }}"
                wire:navigate
                class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('security.edit') ? 'bg-primary/10 text-primary' : 'text-zinc-600 hover:bg-zinc-100' }}"
            >{{ __('Security') }}</a>
        </nav>
    </div>

    <hr class="w-full border-zinc-200 md:hidden">

    <div class="flex-1 self-stretch max-md:pt-6">
        <h2 class="text-base font-semibold text-zinc-900">{{ $heading ?? '' }}</h2>
        <p class="mt-1 text-sm text-zinc-500">{{ $subheading ?? '' }}</p>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
