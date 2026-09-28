<?php

namespace App\Http\Controllers;

use App\Models\TicketAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    /**
     * Stream the attachment from the private disk. Access is checked by the route's `can` middleware.
     */
    public function __invoke(TicketAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name);
    }
}
