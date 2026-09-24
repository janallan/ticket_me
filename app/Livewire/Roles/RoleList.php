<?php

namespace App\Livewire\Roles;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Title('Roles')]
class RoleList extends Component
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
     * Get the roles with their permissions and number of users.
     *
     * @return Collection<int, Role>
     */
    #[Computed]
    public function roles(): Collection
    {
        return Role::query()
            ->with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.roles.role-list');
    }
}
