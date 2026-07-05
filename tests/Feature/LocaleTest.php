<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
