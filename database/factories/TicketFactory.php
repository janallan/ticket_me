<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'department_id' => Department::factory(),
            'ticket_status_id' => TicketStatus::factory(),
            'ticket_priority_id' => TicketPriority::factory(),
            'requester_id' => User::factory(),
            'assignee_id' => null,
            'closed_at' => null,
            'last_activity_at' => now(),
        ];
    }

    /**
     * Indicate that the ticket is in a status that counts as closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_status_id' => TicketStatus::factory()->closed(),
            'closed_at' => now(),
        ]);
    }
}
