<section class="w-full">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Manage user accounts and their roles') }}</flux:subheading>
        </div>

        @can('create', App\Models\User::class)
            <flux:button variant="primary" icon="plus" :href="route('users.create')" wire:navigate data-test="create-user-button">
                {{ __('New user') }}
            </flux:button>
        @endcan
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center">
        <div class="md:flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search name or email')"
                :aria-label="__('Search')"
                clearable
            />
        </div>

        <div class="md:flex-1">
            <flux:select wire:model.live="role" :aria-label="__('Role')">
                <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
                @foreach ($this->roles as $roleOption)
                    <flux:select.option :value="$roleOption->name" wire:key="role-filter-{{ $roleOption->id }}">{{ $roleOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="md:flex-1">
            <flux:select wire:model.live="status" :aria-label="__('Status')">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="deactivated">{{ __('Deactivated') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($roleName = $user->assignedRole()?->name)
                            <flux:badge size="sm">{{ $roleName }}</flux:badge>
                        @else
                            <flux:text variant="subtle">{{ __('No role') }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($user->isActive())
                            <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('Deactivated') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @can('update', $user)
                            <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('users.edit', $user)" wire:navigate>
                                {{ __('Edit') }}
                            </flux:button>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text variant="subtle">{{ __('No users found.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
