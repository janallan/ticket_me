<?php

namespace App\Livewire\Roles;

use App\Actions\Roles\EnsureRoleManagerRemains;
use App\Enums\Permission;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * @property-read Role|null $role
 */
class RoleForm extends Component
{
    #[Locked]
    public ?int $roleId = null;

    public string $name = '';

    /**
     * The names of the permissions selected for the role.
     *
     * @var list<string>
     */
    public array $permissions = [];

    /**
     * Mount the component for creating a new role or editing an existing one.
     */
    public function mount(?Role $role = null): void
    {
        if ($role?->exists) {
            $this->authorize('update', $role);

            $this->roleId = $role->id;
            $this->name = $role->name;
            $this->permissions = $role->permissions->pluck('name')->values()->all();

            return;
        }

        $this->authorize('create', Role::class);
    }

    /**
     * Get the role being edited, if any.
     */
    #[Computed]
    public function role(): ?Role
    {
        return $this->roleId ? Role::withCount('users')->findOrFail($this->roleId) : null;
    }

    /**
     * Create or update the role and sync its permissions.
     */
    public function save(EnsureRoleManagerRemains $ensureRoleManagerRemains): void
    {
        $role = $this->role;

        $role ? $this->authorize('update', $role) : $this->authorize('create', Role::class);

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($this->roleId),
            ],
            'permissions' => ['array'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ]);

        $permissions = array_values(array_unique($validated['permissions'] ?? []));

        if ($role) {
            $ensureRoleManagerRemains->forRole($role, $permissions);
        }

        $role ??= new Role(['guard_name' => 'web']);
        $role->name = $validated['name'];
        $role->save();
        $role->syncPermissions($permissions);

        session()->flash('toast', __('Role :name saved.', ['name' => $role->name]));

        $this->redirectRoute('roles.index', navigate: true);
    }

    /**
     * Delete the role. Only roles without users can be deleted.
     */
    public function delete(): void
    {
        $role = $this->role;

        abort_if($role === null, 404);

        $this->authorize('update', $role);

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'delete' => __('Reassign the users in this role before deleting it.'),
            ]);
        }

        $this->authorize('delete', $role);

        $role->delete();

        session()->flash('toast', __('Role :name deleted.', ['name' => $role->name]));

        $this->redirectRoute('roles.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.roles.role-form');
    }
}
