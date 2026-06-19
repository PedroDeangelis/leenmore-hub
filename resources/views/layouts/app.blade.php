@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white" x-data="{ sidebarOpen: false }">
    <!-- Mobile header -->
    <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-zinc-200 bg-white px-4 py-3 lg:hidden">
        <button type="button" x-on:click="sidebarOpen = true" class="text-zinc-600" aria-label="{{ __('Open menu') }}">
            <x-heroicon-o-bars-3 class="size-6" />
        </button>
        <a href="{{ route('dashboard') }}" class="text-base font-semibold text-primary"
            wire:navigate>{{ config('app.name') }}</a>
    </header>

    <!-- Mobile overlay -->
    <div x-show="sidebarOpen" x-cloak x-on:click="sidebarOpen = false" class="fixed inset-0 z-40 bg-black/40 lg:hidden">
    </div>

    <!-- Sidebar -->
    <aside
        class="fixed inset-y-0  z-50 flex w-64 -translate-x-full flex-col border-e border-white/10 bg-primary transition-transform lg:translate-x-0"
        x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
        <div class="flex items-center justify-between px-5 pt-10 pb-4">
            <a href="{{ route('dashboard') }}" class="block" wire:navigate>
                <img src="{{ asset('images/logo-large.png') }}" alt="Leenmore Hub Logo" />
            </a>
            <button type="button" x-on:click="sidebarOpen = false" class="text-white/70 lg:hidden"
                aria-label="{{ __('Close menu') }}">
                <x-heroicon-o-x-mark class="size-5" />
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2">

            <a href="{{ route('dashboard') }}" wire:navigate
                class="flex items-center gap-3 rounded-lg px-3 py-3 transition text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-white/15 text-white' : 'text-white/80 hover:bg-white/10' }}">
                <x-heroicon-o-home class="size-5" />
                {{ __('Dashboard') }}
            </a>

            @can('view-projects')
                <a href="{{ route('projects.index') }}" wire:navigate
                    class="flex items-center gap-3 rounded-lg px-3 py-3 transition text-sm font-medium {{ request()->routeIs('projects.*') ? 'bg-white/15 text-white' : 'text-white/80 hover:bg-white/10' }}">
                    <x-heroicon-o-megaphone class="size-5" />
                    {{ __('Projects') }}
                </a>
            @endcan

            @can('view-submissions')
                <a href="{{ route('reports.index') }}" wire:navigate
                    class="flex items-center gap-3 rounded-lg px-3 py-3 transition text-sm font-medium {{ request()->routeIs('reports.*') ? 'bg-white/15 text-white' : 'text-white/80 hover:bg-white/10' }}">
                    <x-heroicon-o-document-chart-bar class="size-5" />
                    {{ __('Reports') }}
                </a>
            @endcan

            @can('edit-submissions')
                <a href="{{ route('activity.index') }}" wire:navigate
                    class="flex items-center gap-3 rounded-lg px-3 py-3 transition text-sm font-medium {{ request()->routeIs('activity.*') ? 'bg-white/15 text-white' : 'text-white/80 hover:bg-white/10' }}">
                    <x-heroicon-o-pencil-square class="size-5" />
                    {{ __('Activity reports') }}
                </a>
            @endcan

            @can('manage-users')
                <a href="{{ route('users.index') }}" wire:navigate
                    class="flex items-center gap-3 rounded-lg px-3 py-3 transition text-sm font-medium {{ request()->routeIs('users.*') ? 'bg-white/15 text-white' : 'text-white/80 hover:bg-white/10' }}">
                    <x-heroicon-o-users class="size-5" />
                    {{ __('Users') }}
                </a>
            @endcan
        </nav>

        <!-- User menu -->
        <div class="border-t border-white/10 p-3" x-data="{ menuOpen: false }">
            <div class="relative">
                <button type="button" x-on:click="menuOpen = !menuOpen"
                    class="flex w-full cursor-pointer items-center gap-3 rounded-lg px-2 py-2 text-start hover:bg-white/10"
                    data-test="sidebar-menu-button">
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/20 text-xs font-semibold text-white">
                        {{ auth()->user()->initials() }}
                    </span>
                    <span class="grid flex-1 leading-tight">
                        <span class="truncate text-sm font-medium text-white">{{ auth()->user()->name }}</span>
                        <span class="truncate text-xs text-white/60">{{ auth()->user()->email }}</span>
                    </span>
                    <x-heroicon-o-chevron-up-down class="size-4 text-white/60" />
                </button>

                <div x-show="menuOpen" x-cloak x-on:click.outside="menuOpen = false"
                    class="absolute bottom-full start-0 z-10 mb-2 w-full rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                    <a href="{{ route('profile.edit') }}" wire:navigate
                        class="block px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50">
                        {{ __('Settings') }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="block w-full cursor-pointer px-4 py-2 text-start text-sm text-zinc-700 hover:bg-zinc-50"
                            data-test="logout-button">
                            {{ __('Log out') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main content -->
    <div class="lg:ps-64">
        <main class="p-6">
            {{ $slot }}
        </main>
    </div>

    <x-toast-container />

    @livewireScripts
</body>

</html>
