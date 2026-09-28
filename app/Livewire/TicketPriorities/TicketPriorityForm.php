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

    /**
     * The form's fields, bound in the view as `ticketPriorityForm.<field>`.
     *
     * @var array{name: string, color: string, isDefault: bool, sortOrder: int|string}
     */
    public array $ticketPriorityForm = [
        'name' => '',
        'color' => 'zinc',
        'isDefault' => false,
        'sortOrder' => 0,
    ];

    /**
     * Mount the component for creating a new priority or editing an existing one.
     */
    public function mount(?TicketPriority $ticketPriority = null): void
    {
        if ($ticketPriority?->exists) {
            $this->authorize('update', $ticketPriority);

            $this->priorityId = $ticketPriority->id;
            $this->ticketPriorityForm = [
                'name' => $ticketPriority->name,
                'color' => $ticketPriority->color->value,
                'isDefault' => $ticketPriority->is_default,
                'sortOrder' => $ticketPriority->sort_order,
            ];

            return;
        }

        $this->authorize('create', TicketPriority::class);

        $this->ticketPriorityForm['sortOrder'] = (int) TicketPriority::max('sort_order') + 10;
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
            'ticketPriorityForm.name' => ['required', 'string', 'max:255', Rule::unique('ticket_priorities', 'name')->ignore($this->priorityId)],
            'ticketPriorityForm.color' => ['required', Rule::enum(BadgeColor::class)],
            'ticketPriorityForm.isDefault' => ['boolean'],
            'ticketPriorityForm.sortOrder' => ['required', 'integer', 'min:0', 'max:65535'],
        ], attributes: [
            'ticketPriorityForm.name' => __('name'),
            'ticketPriorityForm.color' => __('color'),
            'ticketPriorityForm.isDefault' => __('default'),
            'ticketPriorityForm.sortOrder' => __('order'),
        ])['ticketPriorityForm'];

        if ($priority?->is_default && ! $validated['isDefault']) {
            throw ValidationException::withMessages([
                'ticketPriorityForm.isDefault' => __('Make another priority the default instead.'),
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
