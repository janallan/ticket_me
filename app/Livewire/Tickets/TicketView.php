<?php

namespace App\Livewire\Tickets;

use App\Actions\Tickets\AssignTicket;
use App\Actions\Tickets\UpdateTicketDetails;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read Ticket $ticket
 * @property-read bool $canWork
 * @property-read Collection<int, TicketStatus> $statuses
 * @property-read Collection<int, TicketPriority> $priorities
 * @property-read Collection<int, Department> $departments
 * @property-read Collection<int, User> $assignableUsers
 */
class TicketView extends Component
{
    #[Locked]
    public int $ticketId;

    /**
     * The details panel, bound in the view as `detailsForm.<field>`; each value is a record ID.
     *
     * @var array{status: string, priority: string, department: string}
     */
    public array $detailsForm = [
        'status' => '',
        'priority' => '',
        'department' => '',
    ];

    /**
     * The assignee select, bound in the view as `assignForm.assignee`. Empty means unassigned.
     *
     * @var array{assignee: string}
     */
    public array $assignForm = [
        'assignee' => '',
    ];

    /**
     * Mount the component for the given ticket.
     */
    public function mount(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);

        $this->ticketId = $ticket->id;
        $this->fillDetails($ticket);

        if (session()->has('toast')) {
            Flux::toast(variant: 'success', text: session('toast'));
        }
    }

    /**
     * Get the ticket with the relations the page shows.
     */
    #[Computed]
    public function ticket(): Ticket
    {
        return Ticket::with(['department', 'status', 'priority', 'requester', 'assignee', 'attachments'])->findOrFail($this->ticketId);
    }

    /**
     * Determine whether the current user works this ticket.
     */
    #[Computed]
    public function canWork(): bool
    {
        return user()->can('work', $this->ticket);
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
     * Get the active departments, plus the ticket's current one.
     *
     * @return Collection<int, Department>
     */
    #[Computed]
    public function departments(): Collection
    {
        return Department::query()
            ->where(fn ($query) => $query->active()->orWhereKey($this->ticket->department_id))
            ->orderBy('name')
            ->get();
    }

    /**
     * Get the active users who can work tickets in the ticket's department.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function assignableUsers(): Collection
    {
        return User::query()
            ->active()
            ->worksTicketsInDepartment($this->ticket->department)
            ->orderBy('name')
            ->get();
    }

    /**
     * Reload the ticket after a reply, which can reopen it or bump its activity.
     */
    #[On('ticket-message-added')]
    public function messageAdded(): void
    {
        $this->refreshTicket();
    }

    /**
     * Save the status, priority and department.
     */
    public function saveDetails(UpdateTicketDetails $updateTicketDetails): void
    {
        $ticket = $this->ticket;

        $this->authorize('work', $ticket);

        $validated = $this->validate([
            'detailsForm.status' => ['required', 'integer', Rule::exists('ticket_statuses', 'id')],
            'detailsForm.priority' => ['required', 'integer', Rule::exists('ticket_priorities', 'id')],
            'detailsForm.department' => ['required', 'integer', Rule::in($this->departments->pluck('id')->all())],
        ], attributes: [
            'detailsForm.status' => __('status'),
            'detailsForm.priority' => __('priority'),
            'detailsForm.department' => __('department'),
        ])['detailsForm'];

        $updateTicketDetails(
            $ticket,
            user(),
            TicketStatus::findOrFail($validated['status']),
            TicketPriority::findOrFail($validated['priority']),
            Department::findOrFail($validated['department']),
        );

        if ($this->leftBecauseNoLongerVisible()) {
            return;
        }

        $this->ticketChanged();

        Flux::toast(variant: 'success', text: __('Ticket details saved.'));
    }

    /**
     * Assign the ticket to the chosen user, or unassign it.
     */
    public function assign(AssignTicket $assignTicket): void
    {
        $ticket = $this->ticket;

        $this->authorize('assign', $ticket);

        $validated = $this->validate([
            'assignForm.assignee' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ], attributes: [
            'assignForm.assignee' => __('assignee'),
        ])['assignForm'];

        $assignee = filled($validated['assignee']) ? User::findOrFail($validated['assignee']) : null;

        $assignTicket($ticket, user(), $assignee, 'assignForm.assignee');

        $this->ticketChanged();

        Flux::toast(variant: 'success', text: $assignee
            ? __('Assigned to :name.', ['name' => $assignee->name])
            : __('The ticket is now unassigned.'));
    }

    /**
     * Assign the unassigned ticket to the current user.
     */
    public function claim(AssignTicket $assignTicket): void
    {
        $ticket = $this->ticket;

        $this->authorize('claim', $ticket);

        $assignTicket($ticket, user(), user());

        $this->ticketChanged();

        Flux::toast(variant: 'success', text: __('You claimed this ticket.'));
    }

    /**
     * Send the user back to the list if a change moved the ticket out of their reach.
     */
    private function leftBecauseNoLongerVisible(): bool
    {
        $ticket = $this->ticket->fresh(['department']);

        if ($ticket && user()->can('view', $ticket)) {
            return false;
        }

        session()->flash('toast', __('Ticket saved. It moved to a department you do not work in.'));

        $this->redirectRoute('tickets.index', navigate: true);

        return true;
    }

    /**
     * Reload the ticket and the form fields after a change.
     */
    private function refreshTicket(): void
    {
        unset($this->ticket, $this->canWork, $this->departments, $this->assignableUsers);

        $this->fillDetails($this->ticket);
    }

    /**
     * Reload after a change here, and tell the thread so it shows the new log entry.
     */
    private function ticketChanged(): void
    {
        $this->refreshTicket();

        $this->dispatch('ticket-updated');
    }

    /**
     * Copy the ticket's details into the form fields.
     */
    private function fillDetails(Ticket $ticket): void
    {
        $this->detailsForm = [
            'status' => (string) $ticket->ticket_status_id,
            'priority' => (string) $ticket->ticket_priority_id,
            'department' => (string) $ticket->department_id,
        ];
        $this->assignForm['assignee'] = (string) $ticket->assignee_id;
    }

    public function render(): View
    {
        return view('livewire.tickets.ticket-view');
    }
}
