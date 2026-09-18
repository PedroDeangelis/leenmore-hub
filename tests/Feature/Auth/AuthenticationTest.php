<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_everyone_lands_on_the_api_portal_after_login(): void
    {
        foreach ([User::factory()->admin(), User::factory()->viewer()] as $factory) {
            $user = $factory->create();

            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('api.tokens', absolute: false));

            $this->assertAuthenticated();
            $this->post(route('logout'));
        }
    }

    public function test_logins_always_set_a_remember_cookie(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1', // always sent as a hidden field on the login form
        ])->assertCookie(auth()->guard('web')->getRecallerName());
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrorsIn('email');

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
