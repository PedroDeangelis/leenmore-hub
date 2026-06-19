<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/settings/profile')->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test(Profile::class)
            ->set('name', 'Test User')
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $this->assertEquals('Test User', $user->refresh()->name);
    }

    public function test_email_address_cannot_be_changed(): void
    {
        $user = User::factory()->create();
        $originalEmail = $user->email;

        $this->actingAs($user);

        $response = Livewire::test(Profile::class)
            ->set('name', 'Test User')
            ->set('email', 'changed@example.com')
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $user->refresh();

        $this->assertEquals($originalEmail, $user->email);
        $this->assertNotNull($user->email_verified_at);
    }
}
