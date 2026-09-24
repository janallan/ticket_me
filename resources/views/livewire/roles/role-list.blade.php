<section class="w-full">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Roles') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Manage roles and the permissions they grant') }}</flux:subheading>
        </div>

        @can('create', Spatie\Permission\Models\Role::class)
            <flux:button variant="primary" icon="plus" :href="route('roles.create')" wire:navigate data-test="create-role-button">
                {{ __('New role') }}
            </flux:button>
        @endcan
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Permissions') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Users') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->roles as $role)
                <flux:table.row :key="$role->id">
                    <flux:table.cell variant="strong">{{ $role->name }}</flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-wrap gap-1">
                            @forelse ($role->permissions->sortBy('name') as $permission)
                                <flux:badge size="sm" wire:key="role-{{ $role->id }}-permission-{{ $permission->id }}">
                                    {{ App\Enums\Permission::tryFrom($permission->name)?->label() ?? $permission->name }}
                                </flux:badge>
                            @empty
                                <flux:text variant="subtle">{{ __('No permissions') }}</flux:text>
                            @endforelse
                        </div>
                    </flux:table.cell>

                    <flux:table.cell align="end">{{ $role->users_count }}</flux:table.cell>

                    <flux:table.cell align="end">
                        @can('update', $role)
                            <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('roles.edit', $role)" wire:navigate>
                                {{ __('Edit') }}
                            </flux:button>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">
                        <flux:text variant="subtle">{{ __('No roles yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
