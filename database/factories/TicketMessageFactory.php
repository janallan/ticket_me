<?php

namespace Database\Factories;

use App\Enums\TicketMessageType;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketMessage>
 */
class TicketMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'type' => TicketMessageType::Message,
            'body' => fake()->paragraph(),
            'is_internal' => false,
        ];
    }

    /**
     * Indicate that the message is an internal note, hidden from the requester.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => true,
        ]);
    }
}
