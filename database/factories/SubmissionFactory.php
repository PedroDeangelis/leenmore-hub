<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $worker = User::factory()->worker();

        return [
            'project_id' => Project::factory(),
            'project_shareholder_id' => ProjectShareholder::factory(),
            'user_id' => $worker,
            'user_name' => fake()->name(),
            'date' => fake()->dateTimeBetween('-2 weeks', 'now'),
            'result' => fake()->randomElement(['위임(대면_서명)', '단순부재', '거부', '위임(비대면_문자)']),
            'contact' => fake()->numerify('010-####-####'),
            'privacy_consent' => fake()->boolean(),
            'note' => fake()->optional()->sentence(),
            'files' => null,
            'privacy_consent_files' => null,
        ];
    }

    /**
     * Tie the report to an existing assignment (and its project + worker name).
     */
    public function forAssignment(ProjectShareholder $assignment): static
    {
        return $this->state(fn (array $attributes): array => [
            'project_id' => $assignment->project_id,
            'project_shareholder_id' => $assignment->id,
        ]);
    }
}
