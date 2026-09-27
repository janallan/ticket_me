<?php

namespace App\Livewire\TicketPriorities;

use App\Models\TicketPriority;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, TicketPriority> $priorities
 */
#[Title('Ticket priorities')]
class TicketPriorityList extends Component
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
     * Get the priorities in display order.
     *
     * @return Collection<int, TicketPriority>
     */
    #[Computed]
    public function priorities(): Collection
    {
        return TicketPriority::query()->ordered()->get();
    }

    public function render(): View
    {
        return view('livewire.ticket-priorities.ticket-priority-list');
    }
}
