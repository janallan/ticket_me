<?php

namespace App\Livewire\Users;

use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/**
 * @property-read LengthAwarePaginator<int, User> $users
 * @property-read Collection<int, Role> $roles
 */
#[Title('Users')]
class UserList extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: '')]
    public string $status = '';

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
     * Go back to the first page whenever a filter changes.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'status'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Get the filtered, paginated users.
     *
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when($this->search !== '', function (Builder $query) {
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%'));
            })
            ->when($this->role !== '', fn (Builder $query) => $query->role($this->role))
            ->when($this->status === 'active', fn (Builder $query) => $query->whereNull('deactivated_at'))
            ->when($this->status === 'deactivated', fn (Builder $query) => $query->whereNotNull('deactivated_at'))
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * Get the roles available for filtering.
     *
     * @return Collection<int, Role>
     */
    #[Computed]
    public function roles(): Collection
    {
        return Role::query()->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.users.user-list');
    }
}
