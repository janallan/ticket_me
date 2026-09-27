<?php

namespace App\Livewire\Departments;

use App\Models\Department;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read Department|null $department
 */
class DepartmentForm extends Component
{
    #[Locked]
    public ?int $departmentId = null;

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    /**
     * Mount the component for creating a new department or editing an existing one.
     */
    public function mount(?Department $department = null): void
    {
        if ($department?->exists) {
            $this->authorize('update', $department);

            $this->departmentId = $department->id;
            $this->name = $department->name;
            $this->description = $department->description ?? '';
            $this->isActive = $department->is_active;

            return;
        }

        $this->authorize('create', Department::class);
    }

    /**
     * Get the department being edited, if any.
     */
    #[Computed]
    public function department(): ?Department
    {
        return $this->departmentId ? Department::withCount('users')->findOrFail($this->departmentId) : null;
    }

    /**
     * Create or update the department. Members are assigned from the user form.
     */
    public function save(): void
    {
        $department = $this->department;

        $department ? $this->authorize('update', $department) : $this->authorize('create', Department::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($this->departmentId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'isActive' => ['boolean'],
        ]);

        $department ??= new Department;
        $department->fill([
            'name' => $validated['name'],
            'description' => $validated['description'] !== '' ? $validated['description'] : null,
            'is_active' => $validated['isActive'],
        ])->save();

        session()->flash('toast', __('Department :name saved.', ['name' => $department->name]));

        $this->redirectRoute('departments.index', navigate: true);
    }

    /**
     * Delete the department.
     */
    public function delete(): void
    {
        $department = $this->department;

        abort_if($department === null, 404);

        $this->authorize('delete', $department);

        $department->delete();

        session()->flash('toast', __('Department :name deleted.', ['name' => $department->name]));

        $this->redirectRoute('departments.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.departments.department-form');
    }
}
