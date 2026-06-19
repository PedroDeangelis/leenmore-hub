<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <x-ui.input name="email" :label="__('Email address')" :value="old('email')" type="email" required autofocus
                autocomplete="email" />

            <!-- Password -->
            <div class="relative">
                <x-ui.input name="password" :label="__('Password')" type="password" required autocomplete="current-password"
                    viewable />

                @if (Route::has('password.request'))
                    <a class="absolute top-0 end-0 text-sm text-primary hover:underline"
                        href="{{ route('password.request') }}" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>

            <!-- Always remember the user -->
            <input type="hidden" name="remember" value="1">

            <div class="flex items-center justify-end">
                <x-ui.button variant="primary" type="submit" class="w-full btn-tall" data-test="login-button">
                    {{ __('Log in') }}
                </x-ui.button>
            </div>
        </form>
    </div>
</x-layouts::auth>
