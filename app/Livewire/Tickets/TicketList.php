<?php

namespace App\Livewire\Tickets;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, Ticket> $tickets
 * @property-read bool $canWorkTickets
 * @property-read Collection<int, TicketStatus> $statuses
 * @property-read Collection<int, TicketPriority> $priorities
 * @property-read Collection<int, Department> $departments
 */
#[Title('Tickets')]
class TicketList extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $priority = '';

    #[Url(except: '')]
    public string $department = '';

    /**
     * Only tickets the user opened.
     */
    #[Url(except: false)]
    public bool $openedByMe = false;

    /**
     * Only tickets assigned to the user. For people who work tickets; cannot be combined with `$unassigned`.
     */
    #[Url(except: false)]
    public bool $assignedToMe = false;

    /**
     * Only tickets nobody is assigned to. For people who work tickets; cannot be combined with `$assignedToMe`.
     */
    #[Url(except: false)]
    public bool $unassigned = false;

    #[Url(except: false)]
    public bool $showClosed = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        if ($this->assignedToMe && $this->unassigned) {
            $this->unassigned = false;
        }

        if (session()->has('toast')) {
            Flux::toast(variant: 'success', text: session('toast'));
        }
    }

    /**
     * Go back to the first page whenever a filter changes. "Assigned to me" and "Unassigned" exclude
     * each other, so turning one on turns the other off.
     */
    public function updated(string $property): void
    {
        if ($property === 'assignedToMe' && $this->assignedToMe) {
            $this->unassigned = false;
        }

        if ($property === 'unassigned' && $this->unassigned) {
            $this->assignedToMe = false;
        }

        if (in_array($property, ['search', 'status', 'priority', 'department', 'openedByMe', 'assignedToMe', 'unassigned', 'showClosed'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Determine whether the assignment switches are offered: only people who work tickets get them.
     */
    #[Computed]
    public function canWorkTickets(): bool
    {
        return user()->canWorkTickets();
    }

    /**
     * Get the filtered, paginated tickets the user can see, most recently active first.
     *
     * @return LengthAwarePaginator<int, Ticket>
     */
    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        $user = user();
        $filtersAssignment = $this->canWorkTickets;

        return Ticket::query()
            ->visibleTo($user)
            ->with(['department', 'status', 'priority', 'assignee'])
            ->when($this->openedByMe, fn (Builder $query) => $query->where('requester_id', $user->id))
            ->when($filtersAssignment && $this->assignedToMe, fn (Builder $query) => $query->where('assignee_id', $user->id))
            ->when($filtersAssignment && $this->unassigned, fn (Builder $query) => $query->whereNull('assignee_id'))
            ->when($this->search !== '', fn (Builder $query) => $this->applySearch($query))
            ->when($this->status !== '', fn (Builder $query) => $query->where('ticket_status_id', $this->status))
            ->when($this->priority !== '', fn (Builder $query) => $query->where('ticket_priority_id', $this->priority))
            ->when($this->department !== '', fn (Builder $query) => $query->where('department_id', $this->department))
            ->when(! $this->showClosed && $this->status === '', fn (Builder $query) => $query->whereRelation('status', 'is_closed', false))
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * @return Collection<int, TicketStatus>
     */
    #[Computed]
    public function statuses(): Collection
    {
        return TicketStatus::query()->ordered()->get();
    }

    /**
     * @return Collection<int, TicketPriority>
     */
    #[Computed]
    public function priorities(): Collection
    {
        return TicketPriority::query()->ordered()->get();
    }

    /**
     * @return Collection<int, Department>
     */
    #[Computed]
    public function departments(): Collection
    {
        return Department::query()->orderBy('name')->get();
    }

    /**
     * Match the subject or description, or the ticket number when the search looks like one ("#12", "000012" or "12").
     *
     * @param  Builder<Ticket>  $query
     */
    private function applySearch(Builder $query): void
    {
        $search = trim($this->search);
        $number = ltrim($search, '#');

        $query->where(function (Builder $query) use ($search, $number) {
            $query->where('subject', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%');

            if ($number !== '' && ctype_digit($number)) {
                $query->orWhere('id', (int) $number);
            }
        });
    }

    public function render(): View
    {
        return view('livewire.tickets.ticket-list');
    }
}
