<?php

namespace App\Livewire\TicketStatuses;

use App\Enums\BadgeColor;
use App\Models\TicketStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read TicketStatus|null $status
 */
class TicketStatusForm extends Component
{
    #[Locked]
    public ?int $statusId = null;

    public string $name = '';

    public string $color = 'zinc';

    public bool $isDefault = false;

    public bool $isClosed = false;

    public int $sortOrder = 0;

    /**
     * Mount the component for creating a new status or editing an existing one.
     */
    public function mount(?TicketStatus $ticketStatus = null): void
    {
        if ($ticketStatus?->exists) {
            $this->authorize('update', $ticketStatus);

            $this->statusId = $ticketStatus->id;
            $this->name = $ticketStatus->name;
            $this->color = $ticketStatus->color->value;
            $this->isDefault = $ticketStatus->is_default;
            $this->isClosed = $ticketStatus->is_closed;
            $this->sortOrder = $ticketStatus->sort_order;

            return;
        }

        $this->authorize('create', TicketStatus::class);

        $this->sortOrder = (int) TicketStatus::max('sort_order') + 10;
    }

    /**
     * Get the status being edited, if any.
     */
    #[Computed]
    public function status(): ?TicketStatus
    {
        return $this->statusId ? TicketStatus::findOrFail($this->statusId) : null;
    }

    /**
     * Create or update the status.
     */
    public function save(): void
    {
        $status = $this->status;

        $status ? $this->authorize('update', $status) : $this->authorize('create', TicketStatus::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('ticket_statuses', 'name')->ignore($this->statusId)],
            'color' => ['required', Rule::enum(BadgeColor::class)],
            'isDefault' => ['boolean'],
            'isClosed' => ['boolean'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        if ($status?->is_default && ! $validated['isDefault']) {
            throw ValidationException::withMessages([
                'isDefault' => __('Make another status the default instead.'),
            ]);
        }

        if ($validated['isDefault'] && $validated['isClosed']) {
            throw ValidationException::withMessages([
                'isClosed' => __('The default status for new tickets cannot count as closed.'),
            ]);
        }

        DB::transaction(function () use (&$status, $validated) {
            $status ??= new TicketStatus;
            $status->fill([
                'name' => $validated['name'],
                'color' => $validated['color'],
                'is_closed' => $validated['isClosed'],
                'sort_order' => $validated['sortOrder'],
            ])->save();

            if ($validated['isDefault'] || TicketStatus::findDefault() === null) {
                $status->makeDefault();
            }
        });

        session()->flash('toast', __('Status :name saved.', ['name' => $status->name]));

        $this->redirectRoute('ticket-statuses.index', navigate: true);
    }

    /**
     * Delete the status. The default status cannot be deleted.
     */
    public function delete(): void
    {
        $status = $this->status;

        abort_if($status === null, 404);

        $this->authorize('delete', $status);

        $status->delete();

        session()->flash('toast', __('Status :name deleted.', ['name' => $status->name]));

        $this->redirectRoute('ticket-statuses.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.ticket-statuses.ticket-status-form');
    }
}
