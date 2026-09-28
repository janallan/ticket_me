<?php

namespace App\Actions\Tickets;

use App\Enums\TicketMessageType;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;

/**
 * Records a change to a ticket as a log entry in its thread, listing the original value of each
 * changed field. This keeps the ticket's history without a separate history table.
 */
class LogTicketChanges
{
    /**
     * Add the log entry, or do nothing when nothing changed.
     *
     * @param  array<string, string|null>  $originalValues  The original values, keyed by field label.
     */
    public function __invoke(Ticket $ticket, User $editor, array $originalValues): ?TicketMessage
    {
        if ($originalValues === []) {
            return null;
        }

        $lines = collect($originalValues)
            ->map(fn (?string $value, string $label) => $label.': '.(filled($value) ? $value : __('(none)')))
            ->implode("\n");

        return $ticket->messages()->create([
            'user_id' => $editor->id,
            'type' => TicketMessageType::Log,
            'body' => $lines,
            'is_internal' => false,
        ]);
    }
}
