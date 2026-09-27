<?php

use App\Livewire\Departments\DepartmentForm;
use App\Livewire\Departments\DepartmentList;
use App\Livewire\TicketPriorities\TicketPriorityForm;
use App\Livewire\TicketPriorities\TicketPriorityList;
use App\Livewire\TicketStatuses\TicketStatusForm;
use App\Livewire\TicketStatuses\TicketStatusList;
use App\Models\Department;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('departments')->name('departments.')->group(function () {
        Route::livewire('/', DepartmentList::class)
            ->middleware('can:viewAny,'.Department::class)
            ->name('index');

        Route::livewire('create', DepartmentForm::class)
            ->middleware('can:create,'.Department::class)
            ->name('create');

        Route::livewire('{department}/edit', DepartmentForm::class)
            ->middleware('can:update,department')
            ->name('edit');
    });

    Route::prefix('ticket-statuses')->name('ticket-statuses.')->group(function () {
        Route::livewire('/', TicketStatusList::class)
            ->middleware('can:viewAny,'.TicketStatus::class)
            ->name('index');

        Route::livewire('create', TicketStatusForm::class)
            ->middleware('can:create,'.TicketStatus::class)
            ->name('create');

        Route::livewire('{ticketStatus}/edit', TicketStatusForm::class)
            ->middleware('can:update,ticketStatus')
            ->name('edit');
    });

    Route::prefix('ticket-priorities')->name('ticket-priorities.')->group(function () {
        Route::livewire('/', TicketPriorityList::class)
            ->middleware('can:viewAny,'.TicketPriority::class)
            ->name('index');

        Route::livewire('create', TicketPriorityForm::class)
            ->middleware('can:create,'.TicketPriority::class)
            ->name('create');

        Route::livewire('{ticketPriority}/edit', TicketPriorityForm::class)
            ->middleware('can:update,ticketPriority')
            ->name('edit');
    });
});
