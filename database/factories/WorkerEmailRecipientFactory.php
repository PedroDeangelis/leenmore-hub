<?php

namespace Database\Factories;

use App\Enums\WorkerEmailRecipientStatus;
use App\Models\User;
use App\Models\WorkerEmail;
use App\Models\WorkerEmailRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkerEmailRecipient>
 */
class WorkerEmailRecipientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'worker_email_id' => WorkerEmail::factory(),
            'user_id' => User::factory()->worker(),
            'email' => fake()->unique()->safeEmail(),
            'status' => WorkerEmailRecipientStatus::Pending,
            'attempts' => 0,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WorkerEmailRecipientStatus::Sent,
            'attempts' => 1,
            'last_attempt_at' => now(),
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WorkerEmailRecipientStatus::Failed,
            'attempts' => 3,
            'last_attempt_at' => now(),
            'error_message' => 'SMTP connection refused',
        ]);
    }
}
