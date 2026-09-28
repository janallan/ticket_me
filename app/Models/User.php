<?php

namespace App\Models;

use App\Enums\Permission;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $default_department_id
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * Get the departments the user belongs to.
     *
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)->withTimestamps();
    }

    /**
     * Get the user's default department, which is always one of their departments.
     *
     * @return BelongsTo<Department, $this>
     */
    public function defaultDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'default_department_id');
    }

    /**
     * Get the tickets the user opened.
     *
     * @return HasMany<Ticket, $this>
     */
    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    /**
     * Get the tickets assigned to the user.
     *
     * @return HasMany<Ticket, $this>
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assignee_id');
    }

    /**
     * Determine whether the user works tickets at all, in their departments or everywhere.
     */
    public function canWorkTickets(): bool
    {
        return $this->can(Permission::Tickets->value) || $this->can(Permission::TicketsAll->value);
    }

    /**
     * Determine whether the user can work tickets in the department: with `tickets-all`,
     * or with `tickets` and membership of the department.
     */
    public function worksTicketsIn(Department $department): bool
    {
        if ($this->can(Permission::TicketsAll->value)) {
            return true;
        }

        return $this->can(Permission::Tickets->value)
            && $this->departments()->whereKey($department->id)->exists();
    }

    /**
     * Scope a query to the users who can work tickets in the department.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function worksTicketsInDepartment(Builder $query, Department $department): void
    {
        $query->where(fn (Builder $query) => $query
            ->holdingPermission(Permission::TicketsAll)
            ->orWhere(fn (Builder $query) => $query
                ->holdingPermission(Permission::Tickets)
                ->whereHas('departments', fn (Builder $departments) => $departments->whereKey($department->id))));
    }

    /**
     * Scope a query to the users granted the permission directly or through a role. Unlike
     * spatie's `permission()` scope, this matches nobody instead of throwing when the permission
     * has not been synced to the database yet.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function holdingPermission(Builder $query, Permission $permission): void
    {
        $named = fn (Builder $permissions) => $permissions->where('name', $permission->value);

        $query->where(fn (Builder $query) => $query
            ->whereHas('permissions', $named)
            ->orWhereHas('roles.permissions', $named));
    }

    /**
     * Get the user's role. Each user has at most one role.
     */
    public function assignedRole(): ?Role
    {
        $role = $this->roles->first();

        return $role instanceof Role ? $role : null;
    }

    /**
     * Determine whether the user's account is active.
     */
    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * Scope a query to only include active users.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('deactivated_at');
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
