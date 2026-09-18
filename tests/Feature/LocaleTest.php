<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The portal is bilingual: every page renders in the signed-in user's saved
 * language. Both directions are pinned so a missing translation key shows up
 * here rather than in front of the client.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_with_korean_locale_see_korean_text(): void
    {
        $user = User::factory()->admin()->create(['locale' => 'ko']);

        $this->actingAs($user)->get(route('api.tokens'))
            ->assertOk()
            ->assertSee('API 접근');
    }

    public function test_users_with_english_locale_see_english_text(): void
    {
        $user = User::factory()->admin()->create(['locale' => 'en']);

        $this->actingAs($user)->get(route('api.tokens'))
            ->assertOk()
            ->assertSee('API access');
    }
}
