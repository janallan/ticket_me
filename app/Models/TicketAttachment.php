<?php

namespace App\Models;

use Database\Factories\TicketAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $ticket_message_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ticket $ticket
 * @property-read TicketMessage|null $message
 */
#[Fillable(['ticket_id', 'ticket_message_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class TicketAttachment extends Model
{
    /** @use HasFactory<TicketAttachmentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the message the file is attached to, or null when it belongs to the ticket's description.
     *
     * @return BelongsTo<TicketMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }

    /**
     * Determine whether the file is attached to an internal note.
     */
    public function isInternal(): bool
    {
        return $this->ticket_message_id !== null && $this->message->is_internal;
    }

    /**
     * Get the file size in a human-readable form, such as "1.2 MB".
     */
    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }
}
