<?php

namespace App\Livewire;

use App\Enums\Permission;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The home page. What it shows depends on how much of the ticket queue the user may see:
 * everything (`tickets-all`), their departments (`tickets`), or only the tickets they opened.
 *
 * @property-read string $scope
 * @property-read array{open: int, unassigned: int, assignedToMe: int, closedThisWeek: int, total: int} $counts
 * @property-read Collection<int, TicketStatus> $openByStatus
 * @property-read Collection<int, Department> $openByDepartment
 * @property-read Collection<int, Ticket> $oldestUnassigned
 * @property-read Collection<int, Ticket> $assignedToMe
 * @property-read Collection<int, Ticket> $recentRequests
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    /**
     * How many tickets each list on the dashboard shows.
     */
    public const LIST_SIZE = 5;

    /**
     * Get which part of the queue the dashboard covers: "all", "departments" or "mine".
     */
    #[Computed]
    public function scope(): string
    {
        return match (true) {
            user()->can(Permission::TicketsAll->value) => 'all',
            user()->can(Permission::Tickets->value) => 'departments',
            default => 'mine',
        };
    }

    /**
     * Count the tickets behind the stat tiles, in a single query over the tickets the user can see.
     *
     * @return array{open: int, unassigned: int, assignedToMe: int, closedThisWeek: int, total: int}
     */
    #[Computed]
    public function counts(): array
    {
        $row = $this->visibleTickets()
            ->join('ticket_statuses', 'ticket_statuses.id', '=', 'tickets.ticket_status_id')
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when ticket_statuses.is_closed = 0 then 1 else 0 end) as open')
            ->selectRaw('sum(case when ticket_statuses.is_closed = 0 and tickets.assignee_id is null then 1 else 0 end) as unassigned')
            ->selectRaw('sum(case when ticket_statuses.is_closed = 0 and tickets.assignee_id = ? then 1 else 0 end) as assigned_to_me', [user()->id])
            ->selectRaw('sum(case when tickets.closed_at >= ? then 1 else 0 end) as closed_this_week', [now()->subDays(7)])
            ->first();

        return [
            'open' => (int) ($row->open ?? 0),
            'unassigned' => (int) ($row->unassigned ?? 0),
            'assignedToMe' => (int) ($row->assigned_to_me ?? 0),
            'closedThisWeek' => (int) ($row->closed_this_week ?? 0),
            'total' => (int) ($row->total ?? 0),
        ];
    }

    /**
     * Get the open statuses with how many visible tickets are in each, in their display order.
     *
     * @return Collection<int, TicketStatus>
     */
    #[Computed]
    public function openByStatus(): Collection
    {
        return TicketStatus::query()
            ->where('is_closed', false)
            ->ordered()
            ->withCount(['tickets' => self::visibleToUser(...)])
            ->get();
    }

    /**
     * Get the departments that have open tickets the user can see, busiest first.
     *
     * @return Collection<int, Department>
     */
    #[Computed]
    public function openByDepartment(): Collection
    {
        return Department::query()
            ->withCount(['tickets as open_tickets_count' => fn (Builder $query) => self::visibleToUser($query)
                ->whereRelation('status', 'is_closed', false)])
            ->orderByDesc('open_tickets_count')
            ->orderBy('name')
            ->get()
            ->filter(fn (Department $department) => $department->getAttribute('open_tickets_count') > 0)
            ->values();
    }

    /**
     * Get the open tickets nobody has picked up yet, waiting longest first.
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function oldestUnassigned(): Collection
    {
        return $this->openTickets()
            ->whereNull('assignee_id')
            ->with(['department', 'priority'])
            ->oldest()
            ->limit(self::LIST_SIZE)
            ->get();
    }

    /**
     * Get the open tickets assigned to the user, most recently active first.
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function assignedToMe(): Collection
    {
        return $this->openTickets()
            ->where('assignee_id', user()->id)
            ->with(['department', 'priority', 'status'])
            ->orderByDesc('last_activity_at')
            ->limit(self::LIST_SIZE)
            ->get();
    }

    /**
     * Get the tickets the user opened, most recently active first, closed ones included.
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function recentRequests(): Collection
    {
        return Ticket::query()
            ->where('requester_id', user()->id)
            ->with(['department', 'status', 'assignee'])
            ->orderByDesc('last_activity_at')
            ->limit(self::LIST_SIZE)
            ->get();
    }

    /**
     * Limit a ticket query, such as a relation count, to the tickets the user can see.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    private static function visibleToUser(Builder $query): Builder
    {
        return $query->visibleTo(user());
    }

    /**
     * @return Builder<Ticket>
     */
    private function visibleTickets(): Builder
    {
        return Ticket::query()->visibleTo(user());
    }

    /**
     * @return Builder<Ticket>
     */
    private function openTickets(): Builder
    {
        return $this->visibleTickets()->whereRelation('status', 'is_closed', false);
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
