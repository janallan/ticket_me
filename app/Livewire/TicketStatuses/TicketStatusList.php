<?php

namespace App\Livewire\TicketStatuses;

use App\Models\TicketStatus;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, TicketStatus> $statuses
 */
#[Title('Ticket statuses')]
class TicketStatusList extends Component
{
    /**
     * Mount the component.
     */
    public function mount(): void
    {
        if (session()->has('toast')) {
            Flux::toast(variant: 'success', text: session('toast'));
        }
    }

    /**
     * Get the statuses in display order.
     *
     * @return Collection<int, TicketStatus>
     */
    #[Computed]
    public function statuses(): Collection
    {
        return TicketStatus::query()->ordered()->get();
    }

    public function render(): View
    {
        return view('livewire.ticket-statuses.ticket-status-list');
    }
}
