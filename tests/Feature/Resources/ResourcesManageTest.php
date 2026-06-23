<?php

namespace Tests\Feature\Resources;

use App\Enums\ResourceType;
use App\Livewire\Resources\Manage;
use App\Models\Project;
use App\Models\ProjectResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ResourcesManageTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_can_open_the_index_but_workers_cannot(): void
    {
        $this->actingAs(User::factory()->office()->create())
            ->get(route('resources.index'))->assertOk();

        $this->actingAs(User::factory()->worker()->create())
            ->get(route('resources.index'))->assertRedirect(route('worker.dashboard'));
    }

    public function test_an_admin_can_add_a_link(): void
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Manage::class, ['project' => $project])
            ->set('linkUrl', 'https://example.com')
            ->set('linkTitle', 'Activity site')
            ->call('addLink')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('project_resources', [
            'project_id' => $project->id,
            'type' => ResourceType::Link->value,
            'title' => 'Activity site',
            'url' => 'https://example.com',
        ]);
    }

    public function test_an_admin_can_upload_a_file(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Manage::class, ['project' => $project])
            ->set('files', [UploadedFile::fake()->image('receipt.png')])
            ->assertHasNoErrors();

        $resource = ProjectResource::where('project_id', $project->id)->first();

        $this->assertNotNull($resource);
        $this->assertSame(ResourceType::File, $resource->type);
        $this->assertSame('receipt.png', $resource->file_name);
        Storage::disk('local')->assertExists($resource->file_path);
    }

    public function test_an_admin_can_edit_and_soft_delete_a_resource(): void
    {
        $project = Project::factory()->create();
        $resource = ProjectResource::factory()->for($project)->link()->create(['title' => 'Old']);

        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(Manage::class, ['project' => $project])
            ->call('editResource', $resource->id)
            ->set('editTitle', 'New title')
            ->set('editUrl', 'https://new.example.com')
            ->call('updateResource')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('project_resources', ['id' => $resource->id, 'title' => 'New title']);

        $component->call('deleteResource', $resource->id);

        $this->assertSoftDeleted($resource);
    }

    public function test_an_admin_can_reorder_resources(): void
    {
        $project = Project::factory()->create();
        $first = ProjectResource::factory()->for($project)->link()->create(['sort_order' => 1]);
        $second = ProjectResource::factory()->for($project)->link()->create(['sort_order' => 2]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Manage::class, ['project' => $project])
            ->call('moveUp', $second->id);

        $this->assertTrue($second->fresh()->sort_order < $first->fresh()->sort_order);
    }
}
