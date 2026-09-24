<?php

namespace App\Livewire\Roles;

use App\Actions\Roles\EnsureRoleManagerRemains;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * @property-read Role|null $role
 * @property-read list<string> $grantablePermissions
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
     * Get the permission names the current user may grant or revoke.
     *
     * @return list<string>
     */
    #[Computed]
    public function grantablePermissions(): array
    {
        return $this->actor()->getAllPermissions()->pluck('name')->values()->all();
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

        $permissions = $this->resolvePermissions($role, $validated['permissions'] ?? []);

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

    /**
     * Combine the submitted permissions with the ones the current user is not allowed to change.
     *
     * Users can only grant or revoke permissions they hold themselves, so any other permission
     * keeps its current state on the role.
     *
     * @param  list<string>  $submitted
     * @return list<string>
     */
    private function resolvePermissions(?Role $role, array $submitted): array
    {
        $grantable = $this->grantablePermissions;

        $granted = array_intersect($submitted, $grantable);

        $locked = $role
            ? array_diff($role->permissions->pluck('name')->all(), $grantable)
            : [];

        return array_values(array_unique([...$granted, ...$locked]));
    }

    /**
     * Get the currently authenticated user.
     */
    private function actor(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function render(): View
    {
        return view('livewire.roles.role-form');
    }
}
