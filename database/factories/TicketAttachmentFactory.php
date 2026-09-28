<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketAttachment>
 */
class TicketAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word().'.pdf';

        return [
            'ticket_id' => Ticket::factory(),
            'ticket_message_id' => null,
            'disk' => 'local',
            'path' => 'tickets/'.fake()->uuid().'.pdf',
            'original_name' => $name,
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 1024 * 1024),
        ];
    }

    /**
     * Attach the file to the given message instead of the ticket's description.
     */
    public function forMessage(TicketMessage $message): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_id' => $message->ticket_id,
            'ticket_message_id' => $message->id,
        ]);
    }
}
