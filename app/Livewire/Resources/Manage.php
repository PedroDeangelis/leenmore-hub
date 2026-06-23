<?php

namespace App\Livewire\Resources;

use App\Enums\ResourceType;
use App\Models\Project;
use App\Models\ProjectResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Manage one project's resource room: add links, upload files, edit/delete and
 * reorder resources. Gated by `manage-resources`.
 */
#[Title('Project resources')]
class Manage extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $projectId;

    // Add-link form.
    public string $linkUrl = '';

    public string $linkTitle = '';

    /** @var array<int, TemporaryUploadedFile> Staged uploads, saved on change. */
    public array $files = [];

    // Right-column state.
    public string $search = '';

    public bool $organizing = false;

    // Inline edit state.
    public ?int $editingId = null;

    public string $editTitle = '';

    public string $editUrl = '';

    public function mount(Project $project): void
    {
        Gate::authorize('manage-resources');

        $this->projectId = $project->id;
    }

    /**
     * Add a link resource (URL + title) at the end of the list.
     */
    public function addLink(): void
    {
        Gate::authorize('manage-resources');

        $validated = $this->validate([
            'linkUrl' => ['required', 'url', 'max:2048'],
            'linkTitle' => ['required', 'string', 'max:255'],
        ]);

        ProjectResource::create([
            'project_id' => $this->projectId,
            'type' => ResourceType::Link,
            'title' => $validated['linkTitle'],
            'url' => $validated['linkUrl'],
            'sort_order' => $this->nextSortOrder(),
        ]);

        $this->reset('linkUrl', 'linkTitle');

        $this->dispatch('toast', message: __('Link added.'), variant: 'success');
    }

    /**
     * Auto-save dropped/selected files, one resource per file.
     */
    public function updatedFiles(): void
    {
        Gate::authorize('manage-resources');

        $this->validate([
            'files.*' => ['file', 'max:10240', 'mimes:png,jpg,jpeg,gif,pdf,csv'],
        ]);

        foreach ($this->files as $file) {
            $name = $file->getClientOriginalName();

            ProjectResource::create([
                'project_id' => $this->projectId,
                'type' => ResourceType::File,
                'title' => $name,
                'file_path' => $file->store('resources/'.$this->projectId, 'local'),
                'file_name' => $name,
                'sort_order' => $this->nextSortOrder(),
            ]);
        }

        $this->reset('files');

        $this->dispatch('toast', message: __('Files uploaded.'), variant: 'success');
    }

    public function editResource(int $id): void
    {
        Gate::authorize('manage-resources');

        $resource = $this->resource($id);
        $this->editingId = $resource->id;
        $this->editTitle = $resource->title;
        $this->editUrl = (string) $resource->url;
    }

    public function updateResource(): void
    {
        Gate::authorize('manage-resources');

        $resource = $this->resource($this->editingId);

        $rules = ['editTitle' => ['required', 'string', 'max:255']];
        if ($resource->isLink()) {
            $rules['editUrl'] = ['required', 'url', 'max:2048'];
        }
        $validated = $this->validate($rules);

        $resource->update([
            'title' => $validated['editTitle'],
            'url' => $resource->isLink() ? $validated['editUrl'] : $resource->url,
        ]);

        $this->cancelEdit();

        $this->dispatch('toast', message: __('Resource updated.'), variant: 'success');
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editTitle', 'editUrl');
    }

    /**
     * Soft-delete a resource. The row stays (deleted_at) and the file is kept —
     * reversible only via the database (no restore UI).
     */
    public function deleteResource(int $id): void
    {
        Gate::authorize('manage-resources');

        $this->resource($id)->delete();

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }

        $this->dispatch('toast', message: __('Resource deleted.'), variant: 'success');
    }

    public function moveUp(int $id): void
    {
        $this->swap($id, -1);
    }

    public function moveDown(int $id): void
    {
        $this->swap($id, 1);
    }

    /**
     * Swap a resource's sort_order with its neighbour in the full ordered list.
     */
    private function swap(int $id, int $direction): void
    {
        Gate::authorize('manage-resources');

        $ordered = $this->resources('')->values();
        $index = $ordered->search(fn (ProjectResource $r): bool => $r->id === $id);

        if ($index === false) {
            return;
        }

        $current = $ordered->get($index);
        $target = $ordered->get($index + $direction);

        if (! $target) {
            return;
        }

        [$current->sort_order, $target->sort_order] = [$target->sort_order, $current->sort_order];
        $current->save();
        $target->save();
    }

    private function nextSortOrder(): int
    {
        return (int) ProjectResource::where('project_id', $this->projectId)->max('sort_order') + 1;
    }

    private function resource(int $id): ProjectResource
    {
        return ProjectResource::where('project_id', $this->projectId)->findOrFail($id);
    }

    /**
     * @return Collection<int, ProjectResource>
     */
    private function resources(string $search): Collection
    {
        return ProjectResource::where('project_id', $this->projectId)
            ->when($search !== '', fn (Builder $q) => $q->where('title', 'like', '%'.$search.'%'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.resources.manage', [
            'project' => Project::findOrFail($this->projectId),
            'resources' => $this->resources(trim($this->search)),
        ]);
    }
}
