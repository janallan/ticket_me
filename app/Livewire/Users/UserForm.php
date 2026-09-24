<?php

namespace App\Livewire\Users;

use App\Actions\Roles\EnsureRoleManagerRemains;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
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
 * @property-read bool $canChangeRole
 */
class UserForm extends Component
{
    use ProfileValidationRules;

    #[Locked]
    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    /**
     * The name of the user's role. Each user has exactly one role.
     */
    public string $role = '';

    /**
     * Mount the component for creating a new user or editing an existing one.
     */
    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            $this->authorize('update', $user);

            $this->userId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->role = $user->assignedRole()->name ?? '';

            return;
        }

        $this->authorize('create', User::class);
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
        $held = $this->actor()->getAllPermissions()->pluck('name');

        return Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role) => $role->permissions->pluck('name')->diff($held)->isEmpty())
            ->values();
    }

    /**
     * Determine whether the role field can be changed for this user.
     */
    #[Computed]
    public function canChangeRole(): bool
    {
        return $this->user === null || $this->actor()->can('changeRole', $this->user);
    }

    /**
     * Create the user, or save changes to an existing user.
     */
    public function save(EnsureRoleManagerRemains $ensureRoleManagerRemains): void
    {
        $user = $this->user;

        $user ? $this->authorize('update', $user) : $this->authorize('create', User::class);

        $changesRole = $user === null
            || ($this->canChangeRole && $this->role !== ($user->assignedRole()->name ?? ''));

        $validated = $this->validate([
            'name' => $this->nameRules(),
            'email' => $this->emailRules($this->userId),
            'role' => $changesRole
                ? ['required', 'string', Rule::in($this->assignableRoles->pluck('name')->all())]
                : ['nullable'],
        ]);

        if ($user === null) {
            $this->createUser($validated['name'], $validated['email'], $validated['role']);

            return;
        }

        if ($changesRole) {
            $ensureRoleManagerRemains->forUser($user, Role::findByName($validated['role']), deactivating: false, errorKey: 'role');
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($changesRole) {
            $user->syncRoles([$validated['role']]);
        }

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
     * Create a new user with the given role and email them a link to set their password.
     */
    private function createUser(string $name, string $email, string $role): void
    {
        $user = new User;

        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Str::password(32),
            'email_verified_at' => now(),
        ])->save();

        $user->syncRoles([$role]);

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
        return view('livewire.users.user-form')
            ->title($this->userId ? __('Edit user') : __('New user'));
    }
}
