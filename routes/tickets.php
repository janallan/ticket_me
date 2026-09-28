<?php

use App\Http\Controllers\TicketAttachmentController;
use App\Livewire\Tickets\TicketForm;
use App\Livewire\Tickets\TicketList;
use App\Livewire\Tickets\TicketView;
use App\Models\Ticket;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('tickets')->name('tickets.')->group(function () {
    Route::livewire('/', TicketList::class)
        ->middleware('can:viewAny,'.Ticket::class)
        ->name('index');

    Route::livewire('create', TicketForm::class)
        ->middleware('can:create,'.Ticket::class)
        ->name('create');

    Route::get('attachments/{attachment}', TicketAttachmentController::class)
        ->middleware('can:view,attachment')
        ->name('attachments.show');

    Route::livewire('{ticket}', TicketView::class)
        ->middleware('can:view,ticket')
        ->whereNumber('ticket')
        ->name('show');

    Route::livewire('{ticket}/edit', TicketForm::class)
        ->middleware('can:update,ticket')
        ->whereNumber('ticket')
        ->name('edit');
});
