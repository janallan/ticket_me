<?php

namespace App\Enums;

/**
 * The kinds of entries in a ticket's thread.
 */
enum TicketMessageType: string
{
    /**
     * A reply, or an internal note when the message is marked internal.
     */
    case Message = 'message';

    /**
     * An automatic entry recording the original values of fields that someone changed.
     */
    case Log = 'log';
}
