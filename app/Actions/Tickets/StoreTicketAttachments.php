<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use Illuminate\Http\UploadedFile;

/**
 * Stores uploaded files for a ticket, or one of its messages, on the private attachments disk.
 */
class StoreTicketAttachments
{
    /**
     * Get the validation rules for an attachments field, using the limits in `config/tickets.php`.
     *
     * @return array<string, list<string>>
     */
    public static function rules(string $field = 'attachments'): array
    {
        $config = config('tickets.attachments');

        return [
            $field => ['array', 'max:'.$config['max_files']],
            $field.'.*' => ['file', 'max:'.$config['max_size_kb'], 'mimes:'.implode(',', $config['mimes'])],
        ];
    }

    /**
     * Store the files under `tickets/{ticket}/` and record them against the message, or against
     * the ticket's description when no message is given.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function __invoke(Ticket $ticket, ?TicketMessage $message, array $files): void
    {
        $disk = config('tickets.attachments.disk');

        foreach ($files as $file) {
            $path = $file->store('tickets/'.$ticket->id, $disk);

            TicketAttachment::create([
                'ticket_id' => $ticket->id,
                'ticket_message_id' => $message?->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }
}
