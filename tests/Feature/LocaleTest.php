<?php

namespace Tests\Feature;

use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_with_korean_locale_see_korean_text(): void
    {
        $user = User::factory()->worker()->create(['locale' => 'ko']);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertOk()->assertSee('활동 프로젝트');
    }

    public function test_users_with_english_locale_see_english_text(): void
    {
        $user = User::factory()->worker()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->get(route('worker.dashboard'));

        $response->assertOk()->assertSee('Your projects');
    }

    public function test_users_can_change_their_language_in_profile_settings(): void
    {
        $user = User::factory()->create(['locale' => 'ko']);

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->set('locale', 'en')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_invalid_languages_are_rejected(): void
    {
        $user = User::factory()->create(['locale' => 'ko']);

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->set('locale', 'fr')
            ->call('updateProfileInformation')
            ->assertHasErrors(['locale']);

        $this->assertSame('ko', $user->fresh()->locale);
    }
}
