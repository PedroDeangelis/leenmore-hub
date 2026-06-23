<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-zinc-100">
    {{-- Phone-width column, centered on larger screens. --}}
    <div class="relative mx-auto min-h-screen  bg-zinc-50 pb-20 shadow-sm sm:my-0">
        {{ $slot }}
    </div>

    {{-- Bottom tab bar (fixed, centered to the column). --}}
    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-200 bg-white">
        <nav class="mx-auto flex max-w-md ">
            <a href="{{ route('worker.dashboard') }}" wire:navigate
                class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px] font-semibold transition {{ request()->routeIs('worker.dashboard') ? 'text-primary' : 'text-zinc-400 hover:text-zinc-600' }}">
                <x-heroicon-o-home class="size-5" />{{ __('Home') }}
            </a>
            <a href="{{ route('worker.receipts.create') }}" wire:navigate
                class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px] font-semibold transition {{ request()->routeIs('worker.receipts.create') ? 'text-primary' : 'text-zinc-400 hover:text-zinc-600' }}">
                <x-heroicon-o-receipt-percent class="size-5" />{{ __('Receipt submit') }}
            </a>
            <a href="{{ route('worker.receipts.index') }}" wire:navigate
                class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px] font-semibold transition {{ request()->routeIs('worker.receipts.index') ? 'text-primary' : 'text-zinc-400 hover:text-zinc-600' }}">
                <x-heroicon-o-clipboard-document-list class="size-5" />{{ __('View receipt history') }}
            </a>
            <a href="{{ route('worker.resources.index') }}" wire:navigate
                class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px] font-semibold transition text-center {{ request()->routeIs('worker.resources.*') ? 'text-primary' : 'text-zinc-400 hover:text-zinc-600' }}">
                <x-heroicon-o-folder class="size-5" />{{ __('Project resources') }}
            </a>
            <form method="POST" action="{{ route('logout') }}" class="flex flex-1">
                @csrf
                <button type="submit"
                    class="flex flex-1 cursor-pointer flex-col items-center gap-0.5 py-2.5 text-[11px] font-semibold text-zinc-400 transition hover:text-zinc-600"
                    data-test="logout-button">
                    <x-heroicon-o-arrow-right-start-on-rectangle class="size-5" />{{ __('Log out') }}
                </button>
            </form>
        </nav>
    </div>

    <x-toast-container />

    @livewireScripts
</body>

</html>
