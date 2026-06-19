<?php

namespace App\Livewire\Projects;

use App\Concerns\ProjectValidationRules;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectResult;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Add project')]
class Create extends Component
{
    use ProjectValidationRules;

    public string $title = '';

    public string $status = ProjectStatus::Draft->value;

    public ?string $message = '';

    public ?string $start_date = null;

    public ?string $end_date = null;

    public ?int $shares_issued = null;

    public ?int $shares_target = null;

    /**
     * Create the project and open its detail page.
     */
    public function save(): mixed
    {
        Gate::authorize('manage-projects');

        $validated = $this->validate($this->projectRules());

        $project = Project::create([
            'title' => $validated['title'],
            'status' => $validated['status'],
            'message' => $validated['message'] ?: null,
            'start_date' => $validated['start_date'] ?: null,
            'end_date' => $validated['end_date'] ?: null,
            'shares_issued' => $validated['shares_issued'],
            'shares_target' => $validated['shares_target'],
        ]);

        // Seed the standard 판단 result set so the new project is usable.
        $project->results()->createMany(ProjectResult::defaultSet());

        session()->flash('toast', ['message' => __('Project created.'), 'variant' => 'success']);

        return $this->redirectRoute('projects.show', $project, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.projects.create');
    }
}
