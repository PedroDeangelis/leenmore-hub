<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Options;
use App\Models\ReceiptCategory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_open_the_options_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('options.edit'))
            ->assertOk();
    }

    public function test_office_users_cannot_open_the_options_page(): void
    {
        $this->actingAs(User::factory()->office()->create())
            ->get(route('options.edit'))
            ->assertForbidden();
    }

    public function test_workers_cannot_open_the_options_page(): void
    {
        $this->actingAs(User::factory()->worker()->create())
            ->get(route('options.edit'))
            ->assertRedirect(route('worker.dashboard'));
    }

    public function test_an_admin_can_edit_the_announcement(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Options::class)
            ->set('announcement', 'New notice')
            ->call('saveAnnouncement')
            ->assertHasNoErrors();

        $this->assertSame('New notice', Setting::get('receipt_announcement'));
    }

    public function test_an_admin_can_add_rename_and_delete_a_category(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(Options::class)
            ->set('newCategory', '비품')
            ->call('addCategory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('receipt_categories', ['name' => '비품']);

        $category = ReceiptCategory::firstWhere('name', '비품');

        $component->call('editCategory', $category->id)
            ->set('editingCategoryName', '소모품')
            ->call('updateCategory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('receipt_categories', ['id' => $category->id, 'name' => '소모품']);

        $component->call('deleteCategory', $category->id);

        $this->assertDatabaseMissing('receipt_categories', ['id' => $category->id]);
    }
}
