<section class="w-full">
    <x-slot:title>{{ $this->department ? __('Edit department') : __('New department') }}</x-slot:title>

    <div class="mb-6">
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item :href="route('departments.index')" wire:navigate>{{ __('Departments') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $this->department ? $this->department->name : __('New department') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:heading size="xl" level="1">{{ $this->department ? __('Edit department') : __('New department') }}</flux:heading>
        <flux:subheading size="lg">{{ __('Name the department and choose whether it takes new tickets') }}</flux:subheading>
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <form wire:submit="save" class="w-full max-w-lg space-y-6">
        <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />

        <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

        <flux:switch wire:model="isActive" :label="__('Active')"
            :description="__('Inactive departments can no longer receive new tickets.')" />

        @if ($this->department)
            <flux:field>
                <flux:label>{{ __('Members') }}</flux:label>
                <flux:description>{{ __('Members are assigned from each user\'s page.') }}</flux:description>

                <div class="flex items-center gap-3">
                    <flux:text>{{ trans_choice(':count member|:count members', $this->department->users_count) }}</flux:text>

                    @can('viewAny', App\Models\User::class)
                        <flux:button size="sm" variant="ghost" icon="users"
                            :href="route('users.index', ['department' => $this->department->id])" wire:navigate>
                            {{ __('View members') }}
                        </flux:button>
                    @endcan
                </div>
            </flux:field>
        @endif

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit" data-test="save-department-button">
                {{ __('Save') }}
            </flux:button>

            <flux:button variant="ghost" :href="route('departments.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>

    @if ($this->department)
        <x-settings-delete-section
            :heading="__('Delete department')"
            modal="confirm-department-deletion"
            :can-delete="auth()->user()->can('delete', $this->department)"
            :confirm-heading="__('Delete the :name department?', ['name' => $this->department->name])"
            :confirm-description="__('To keep its history but stop new tickets, mark it inactive instead.')"
        />
    @endif
</section>
