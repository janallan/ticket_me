<section class="w-full">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Departments') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Group tickets by team and choose who works them') }}</flux:subheading>
        </div>

        @can('create', App\Models\Department::class)
            <flux:button variant="primary" icon="plus" :href="route('departments.create')" wire:navigate data-test="create-department-button">
                {{ __('New department') }}
            </flux:button>
        @endcan
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Description') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Members') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->departments as $department)
                <flux:table.row :key="$department->id">
                    <flux:table.cell variant="strong">{{ $department->name }}</flux:table.cell>
                    <flux:table.cell class="max-w-md truncate">{{ $department->description }}</flux:table.cell>
                    <flux:table.cell align="end">
                        @can('viewAny', App\Models\User::class)
                            <flux:link :href="route('users.index', ['department' => $department->id])" wire:navigate>{{ $department->users_count }}</flux:link>
                        @else
                            {{ $department->users_count }}
                        @endcan
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($department->is_active)
                            <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('Inactive') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @can('update', $department)
                            <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('departments.edit', $department)" wire:navigate>
                                {{ __('Edit') }}
                            </flux:button>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text variant="subtle">{{ __('No departments yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
