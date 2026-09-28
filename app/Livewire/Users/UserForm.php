<?php

namespace App\Livewire\Users;

use App\Actions\Roles\EnsureRoleManagerRemains;
use App\Actions\Users\SyncUserDepartments;
use App\Concerns\ProfileValidationRules;
use App\Models\Department;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * @property-read User|null $user
 * @property-read Collection<int, Role> $assignableRoles
 * @property-read Collection<int, Department> $availableDepartments
 * @property-read Collection<int, Department> $chosenDepartments
 * @property-read bool $canChangeRole
 */
class UserForm extends Component
{
    use ProfileValidationRules;

    #[Locked]
    public ?int $userId = null;

    /**
     * The form's fields, bound in the view as `userForm.<field>`. `role` is the name of the user's
     * only role, `departments` holds the IDs of every department the user belongs to, and
     * `defaultDepartment` must be one of them.
     *
     * @var array{name: string, email: string, role: string, departments: list<string>, defaultDepartment: string}
     */
    public array $userForm = [
        'name' => '',
        'email' => '',
        'role' => '',
        'departments' => [],
        'defaultDepartment' => '',
    ];

    /**
     * Mount the component for creating a new user or editing an existing one.
     */
    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            $this->authorize('update', $user);

            $this->userId = $user->id;
            $this->userForm = [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->assignedRole()->name ?? '',
                'departments' => $user->departments()->pluck('departments.id')->map(fn (int $id) => (string) $id)->all(),
                'defaultDepartment' => (string) $user->default_department_id,
            ];

            return;
        }

        $this->authorize('create', User::class);
    }

    /**
     * Keep the default department within the chosen departments whenever the departments change,
     * picking the first chosen one when the current default is no longer ticked.
     */
    public function updatedUserForm(mixed $value, string $key): void
    {
        if (! str_starts_with($key, 'departments')) {
            return;
        }

        if (in_array($this->userForm['defaultDepartment'], $this->userForm['departments'], true)) {
            return;
        }

        $firstChosen = $this->chosenDepartments->first();

        $this->userForm['defaultDepartment'] = $firstChosen ? (string) $firstChosen->id : '';
    }

    /**
     * Get the user being edited, if any.
     */
    #[Computed]
    public function user(): ?User
    {
        return $this->userId ? User::with('roles')->findOrFail($this->userId) : null;
    }

    /**
     * Get the roles the current user may assign: those that grant no permission the current user lacks.
     *
     * @return Collection<int, Role>
     */
    #[Computed]
    public function assignableRoles(): Collection
    {
        $held = user()->getAllPermissions()->pluck('name');

        return Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role) => $role->permissions->pluck('name')->diff($held)->isEmpty())
            ->values();
    }

    /**
     * Get the active departments the user can join, plus any they already belong to.
     *
     * @return Collection<int, Department>
     */
    #[Computed]
    public function availableDepartments(): Collection
    {
        return Department::query()
            ->where(fn ($query) => $query->active()->orWhereIn('id', $this->userForm['departments']))
            ->orderBy('name')
            ->get();
    }

    /**
     * Get the departments currently ticked on the form, in display order.
     *
     * @return Collection<int, Department>
     */
    #[Computed]
    public function chosenDepartments(): Collection
    {
        return $this->availableDepartments
            ->filter(fn (Department $department) => in_array((string) $department->id, $this->userForm['departments'], true))
            ->values();
    }

    /**
     * Determine whether the role field can be changed for this user.
     */
    #[Computed]
    public function canChangeRole(): bool
    {
        return $this->user === null || user()->can('changeRole', $this->user);
    }

    /**
     * Create the user, or save changes to an existing user.
     */
    public function save(EnsureRoleManagerRemains $ensureRoleManagerRemains, SyncUserDepartments $syncUserDepartments): void
    {
        $user = $this->user;

        $user ? $this->authorize('update', $user) : $this->authorize('create', User::class);

        $changesRole = $user === null
            || ($this->canChangeRole && $this->userForm['role'] !== ($user->assignedRole()->name ?? ''));

        $validated = $this->validate([
            'userForm.name' => $this->nameRules(),
            'userForm.email' => $this->emailRules($this->userId),
            'userForm.role' => $changesRole
                ? ['required', 'string', Rule::in($this->assignableRoles->pluck('name')->all())]
                : ['nullable'],
            'userForm.departments' => ['required', 'array', 'min:1'],
            'userForm.departments.*' => ['integer', Rule::exists('departments', 'id')],
            'userForm.defaultDepartment' => ['required', Rule::in($this->userForm['departments'])],
        ], [
            'userForm.departments.required' => __('Choose at least one department.'),
            'userForm.defaultDepartment.in' => __('The default department must be one of the user\'s departments.'),
        ], [
            'userForm.name' => __('name'),
            'userForm.email' => __('email'),
            'userForm.role' => __('role'),
            'userForm.departments' => __('departments'),
            'userForm.departments.*' => __('department'),
            'userForm.defaultDepartment' => __('default department'),
        ])['userForm'];

        if ($user === null) {
            $this->createUser($syncUserDepartments, $validated['name'], $validated['email'], $validated['role'], $validated['departments'], (int) $validated['defaultDepartment']);

            return;
        }

        if ($changesRole) {
            $ensureRoleManagerRemains->forUser($user, Role::findByName($validated['role']), deactivating: false, errorKey: 'userForm.role');
        }

        DB::transaction(function () use ($user, $validated, $changesRole, $syncUserDepartments) {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            if ($changesRole) {
                $user->syncRoles([$validated['role']]);
            }

            $syncUserDepartments($user, $validated['departments'], (int) $validated['defaultDepartment'], 'userForm.defaultDepartment');
        });

        unset($this->user);

        Flux::toast(variant: 'success', text: __('User saved.'));
    }

    /**
     * Deactivate the user so they can no longer sign in.
     */
    public function deactivate(EnsureRoleManagerRemains $ensureRoleManagerRemains): void
    {
        $user = $this->editedUser();

        $this->authorize('deactivate', $user);

        $ensureRoleManagerRemains->forUser($user, $user->assignedRole(), deactivating: true, errorKey: 'deactivate');

        $user->forceFill(['deactivated_at' => now()])->save();

        unset($this->user);

        Flux::modal('confirm-user-deactivation')->close();

        Flux::toast(variant: 'success', text: __(':name has been deactivated.', ['name' => $user->name]));
    }

    /**
     * Reactivate a deactivated user.
     */
    public function reactivate(): void
    {
        $user = $this->editedUser();

        $this->authorize('deactivate', $user);

        $user->forceFill(['deactivated_at' => null])->save();

        unset($this->user);

        Flux::toast(variant: 'success', text: __(':name has been reactivated.', ['name' => $user->name]));
    }

    /**
     * Email the user a new link to set their password.
     */
    public function sendPasswordLink(): void
    {
        $user = $this->editedUser();

        $this->authorize('update', $user);

        $this->notifyToSetPassword($user);

        Flux::toast(variant: 'success', text: __('A set-password link has been sent to :email.', ['email' => $user->email]));
    }

    /**
     * Create a new user with the given role and departments, and email them a link to set their password.
     *
     * @param  list<int|string>  $departments
     */
    private function createUser(SyncUserDepartments $syncUserDepartments, string $name, string $email, string $role, array $departments, int $defaultDepartmentId): void
    {
        $user = DB::transaction(function () use ($syncUserDepartments, $name, $email, $role, $departments, $defaultDepartmentId) {
            $user = new User;

            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => Str::password(32),
                'email_verified_at' => now(),
            ])->save();

            $user->syncRoles([$role]);
            $syncUserDepartments($user, $departments, $defaultDepartmentId, 'userForm.defaultDepartment');

            return $user;
        });

        $this->notifyToSetPassword($user);

        session()->flash('toast', __('User :name created. A set-password link has been sent to :email.', [
            'name' => $user->name,
            'email' => $user->email,
        ]));

        $this->redirectRoute('users.index', navigate: true);
    }

    /**
     * Send the user a link to set their password.
     */
    private function notifyToSetPassword(User $user): void
    {
        $user->notify(new SetPasswordNotification(Password::broker()->createToken($user)));
    }

    /**
     * Get the user being edited, failing when the form is in create mode.
     */
    private function editedUser(): User
    {
        $user = $this->user;

        abort_if($user === null, 404);

        return $user;
    }

    public function render(): View
    {
        return view('livewire.users.user-form');
    }
}
