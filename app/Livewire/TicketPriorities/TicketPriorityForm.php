<?php

namespace App\Livewire\TicketPriorities;

use App\Enums\BadgeColor;
use App\Models\TicketPriority;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read TicketPriority|null $priority
 */
class TicketPriorityForm extends Component
{
    #[Locked]
    public ?int $priorityId = null;

    public string $name = '';

    public string $color = 'zinc';

    public bool $isDefault = false;

    public int $sortOrder = 0;

    /**
     * Mount the component for creating a new priority or editing an existing one.
     */
    public function mount(?TicketPriority $ticketPriority = null): void
    {
        if ($ticketPriority?->exists) {
            $this->authorize('update', $ticketPriority);

            $this->priorityId = $ticketPriority->id;
            $this->name = $ticketPriority->name;
            $this->color = $ticketPriority->color->value;
            $this->isDefault = $ticketPriority->is_default;
            $this->sortOrder = $ticketPriority->sort_order;

            return;
        }

        $this->authorize('create', TicketPriority::class);

        $this->sortOrder = (int) TicketPriority::max('sort_order') + 10;
    }

    /**
     * Get the priority being edited, if any.
     */
    #[Computed]
    public function priority(): ?TicketPriority
    {
        return $this->priorityId ? TicketPriority::findOrFail($this->priorityId) : null;
    }

    /**
     * Create or update the priority.
     */
    public function save(): void
    {
        $priority = $this->priority;

        $priority ? $this->authorize('update', $priority) : $this->authorize('create', TicketPriority::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('ticket_priorities', 'name')->ignore($this->priorityId)],
            'color' => ['required', Rule::enum(BadgeColor::class)],
            'isDefault' => ['boolean'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        if ($priority?->is_default && ! $validated['isDefault']) {
            throw ValidationException::withMessages([
                'isDefault' => __('Make another priority the default instead.'),
            ]);
        }

        DB::transaction(function () use (&$priority, $validated) {
            $priority ??= new TicketPriority;
            $priority->fill([
                'name' => $validated['name'],
                'color' => $validated['color'],
                'sort_order' => $validated['sortOrder'],
            ])->save();

            if ($validated['isDefault'] || TicketPriority::findDefault() === null) {
                $priority->makeDefault();
            }
        });

        session()->flash('toast', __('Priority :name saved.', ['name' => $priority->name]));

        $this->redirectRoute('ticket-priorities.index', navigate: true);
    }

    /**
     * Delete the priority. The default priority cannot be deleted.
     */
    public function delete(): void
    {
        $priority = $this->priority;

        abort_if($priority === null, 404);

        $this->authorize('delete', $priority);

        $priority->delete();

        session()->flash('toast', __('Priority :name deleted.', ['name' => $priority->name]));

        $this->redirectRoute('ticket-priorities.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.ticket-priorities.ticket-priority-form');
    }
}
