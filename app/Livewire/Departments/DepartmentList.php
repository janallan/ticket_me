<?php

namespace App\Livewire\Departments;

use App\Models\Department;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, Department> $departments
 */
#[Title('Departments')]
class DepartmentList extends Component
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
     * Get the departments with their number of members.
     *
     * @return Collection<int, Department>
     */
    #[Computed]
    public function departments(): Collection
    {
        return Department::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.departments.department-list');
    }
}
