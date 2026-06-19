@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-primary antialiased">
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
        <div class="flex w-full max-w-sm flex-col items-center gap-6">
            <img src="{{ asset('images/logo-large.png') }}" alt="Leenmore Hub Logo" class="h-20 w-auto" />
            <div class="flex flex-col gap-6 rounded-xl bg-white p-8 shadow-lg w-full">
                {{ $slot }}
            </div>
        </div>
    </div>

    <x-toast-container />

    @livewireScripts
</body>

</html>
