<?php

namespace Database\Factories;

use App\Enums\RosterSortColumn;
use App\Enums\WorkerEmailStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkerEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkerEmail>
 */
class WorkerEmailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory()->admin(),
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'links' => [],
            'attachments' => [],
            'include_roster_xlsx' => false,
            'include_roster_pdf' => false,
            'roster_sort_column' => RosterSortColumn::No,
            'roster_sort_desc' => false,
            'status' => WorkerEmailStatus::Pending,
        ];
    }

    /**
     * A batch that attaches each worker's 명부양식 (XLSX + PDF).
     */
    public function withRoster(): static
    {
        return $this->state(fn (array $attributes): array => [
            'include_roster_xlsx' => true,
            'include_roster_pdf' => true,
        ]);
    }
}
