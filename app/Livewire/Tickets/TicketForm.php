<?php

namespace App\Livewire\Tickets;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\EditTicket;
use App\Actions\Tickets\StoreTicketAttachments;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * @property-read Ticket|null $ticket
 * @property-read Collection<int, Department> $departments
 * @property-read Collection<int, TicketPriority> $priorities
 * @property-read Collection<int, TicketStatus> $statuses
 * @property-read bool $canChooseStatus
 */
class TicketForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $ticketId = null;

    /**
     * The form's fields, bound in the view as `ticketForm.<field>`. Only people who work tickets in the
     * department can set the status; everyone else keeps the default (when opening) or the current
     * status (when editing). Attachments can only be added when opening a ticket.
     *
     * @var array{subject: string, description: string, department: string, priority: string, status: string, attachments: array<int, UploadedFile>}
     */
    public array $ticketForm = [
        'subject' => '',
        'description' => '',
        'department' => '',
        'priority' => '',
        'status' => '',
        'attachments' => [],
    ];

    /**
     * Mount the component for opening a new ticket or editing an existing one. New tickets start on
     * the requester's default department and the default status and priority.
     */
    public function mount(?Ticket $ticket = null): void
    {
        if ($ticket?->exists) {
            $this->authorize('update', $ticket);

            $this->ticketId = $ticket->id;
            $this->ticketForm = [
                ...$this->ticketForm,
                'subject' => $ticket->subject,
                'description' => $ticket->description ?? '',
                'department' => (string) $ticket->department_id,
                'priority' => (string) $ticket->ticket_priority_id,
                'status' => (string) $ticket->ticket_status_id,
            ];

            return;
        }

        $this->authorize('create', Ticket::class);

        $defaultDepartment = user()->defaultDepartment;

        if ($defaultDepartment?->is_active) {
            $this->ticketForm['department'] = (string) $defaultDepartment->id;
        }

        $this->ticketForm['priority'] = (string) TicketPriority::findDefault()?->id;
        $this->ticketForm['status'] = (string) TicketStatus::findDefault()?->id;
    }

    /**
     * Get the ticket being edited, if any.
     */
    #[Computed]
    public function ticket(): ?Ticket
    {
        return $this->ticketId
            ? Ticket::with(['department', 'status', 'priority', 'attachments'])->findOrFail($this->ticketId)
            : null;
    }

    /**
     * Get the active departments a ticket can be filed under, plus the edited ticket's current one.
     *
     * @return Collection<int, Department>
     */
    #[Computed]
    public function departments(): Collection
    {
        return Department::query()
            ->where(fn ($query) => $query->active()->when($this->ticket, fn ($query, Ticket $ticket) => $query->orWhereKey($ticket->department_id)))
            ->orderBy('name')
            ->get();
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
     * @return Collection<int, TicketStatus>
     */
    #[Computed]
    public function statuses(): Collection
    {
        return TicketStatus::query()->ordered()->get();
    }

    /**
     * Determine whether the status field is shown. The chosen department is checked again on save.
     */
    #[Computed]
    public function canChooseStatus(): bool
    {
        return $this->ticket
            ? user()->can('work', $this->ticket)
            : user()->canWorkTickets();
    }

    /**
     * Remove a file from the pending attachments.
     */
    public function removeAttachment(int $index): void
    {
        unset($this->ticketForm['attachments'][$index]);

        $this->ticketForm['attachments'] = array_values($this->ticketForm['attachments']);
    }

    /**
     * Open the ticket, or save the changes to the edited ticket, and go to it.
     */
    public function save(CreateTicket $createTicket, EditTicket $editTicket): void
    {
        $ticket = $this->ticket;

        $ticket ? $this->authorize('update', $ticket) : $this->authorize('create', Ticket::class);

        $validated = $this->validate([
            'ticketForm.subject' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tickets', 'subject')
                    ->where('requester_id', user()->id)
                    ->whereNull('closed_at')
                    ->ignore($ticket?->id),
            ],
            'ticketForm.department' => ['required', 'integer', Rule::in($this->departments->pluck('id')->all())],
            'ticketForm.priority' => ['required', 'integer', Rule::exists('ticket_priorities', 'id')],
            'ticketForm.status' => ['required', 'integer', Rule::exists('ticket_statuses', 'id')],
            'ticketForm.description' => ['nullable', 'string', 'max:20000'],
            ...($ticket ? [] : StoreTicketAttachments::rules('ticketForm.attachments')),
        ], [
            'ticketForm.subject.unique' => __('You already have an open ticket with this subject.'),
        ], [
            'ticketForm.subject' => __('subject'),
            'ticketForm.department' => __('department'),
            'ticketForm.priority' => __('priority'),
            'ticketForm.status' => __('status'),
            'ticketForm.description' => __('description'),
            'ticketForm.attachments' => __('attachments'),
            'ticketForm.attachments.*' => __('attachment'),
        ])['ticketForm'];

        $department = Department::findOrFail($validated['department']);
        $status = TicketStatus::findOrFail($validated['status']);
        $priority = TicketPriority::findOrFail($validated['priority']);
        $description = filled($validated['description']) ? $validated['description'] : null;

        $this->ensureStatusMayBeSet($status, $department, $ticket);

        if ($ticket === null) {
            $ticket = $createTicket(
                requester: user(),
                department: $department,
                subject: $validated['subject'],
                description: $description,
                priority: $priority,
                attachments: $this->ticketForm['attachments'],
                status: $status,
            );

            session()->flash('toast', __('Ticket :number opened.', ['number' => $ticket->number]));

            $this->redirectRoute('tickets.show', $ticket, navigate: true);

            return;
        }

        $editTicket($ticket, user(), $validated['subject'], $description, $status, $priority, $department);

        session()->flash('toast', __('Ticket :number saved.', ['number' => $ticket->number]));

        $this->redirectRoute('tickets.show', $ticket, navigate: true);
    }

    /**
     * Only people who work tickets in the department may choose the status: anyone else must keep
     * the default when opening a ticket, or the current status when editing one.
     *
     * @throws ValidationException
     */
    private function ensureStatusMayBeSet(TicketStatus $status, Department $department, ?Ticket $ticket): void
    {
        $unchanged = $ticket ? $status->id === $ticket->ticket_status_id : $status->is_default;

        if ($unchanged || user()->worksTicketsIn($department)) {
            return;
        }

        throw ValidationException::withMessages([
            'ticketForm.status' => __('Only people who work tickets in :department can change the status.', ['department' => $department->name]),
        ]);
    }

    public function render(): View
    {
        return view('livewire.tickets.ticket-form');
    }
}
