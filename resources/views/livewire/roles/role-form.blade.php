<section class="w-full">
    <x-slot:title>{{ $this->role ? __('Edit role') : __('New role') }}</x-slot:title>

    <div class="mb-6">
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item :href="route('roles.index')" wire:navigate>{{ __('Roles') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $this->role ? $this->role->name : __('New role') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:heading size="xl" level="1">{{ $this->role ? __('Edit role') : __('New role') }}</flux:heading>
        <flux:subheading size="lg">{{ __('Name the role and choose what it can access') }}</flux:subheading>
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <form wire:submit="save" class="w-full max-w-lg space-y-6">
        <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />

        <flux:checkbox.group wire:model="permissions" :label="__('Permissions')">
            @foreach (App\Enums\Permission::cases() as $permission)
                <flux:checkbox
                    :value="$permission->value"
                    :label="$permission->label()"
                    :description="$permission->description()"
                    :disabled="! in_array($permission->value, $this->grantablePermissions, true)"
                    wire:key="permission-{{ $permission->value }}"
                />
            @endforeach
        </flux:checkbox.group>

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit" data-test="save-role-button">
                {{ __('Save') }}
            </flux:button>

            <flux:button variant="ghost" :href="route('roles.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>

    @if ($this->role)
        <section class="mt-12 max-w-lg space-y-4">
            <div>
                <flux:heading>{{ __('Delete role') }}</flux:heading>

                @if ($this->role->users_count > 0)
                    <flux:subheading>
                        {{ trans_choice('Reassign the :count user in this role before deleting it.|Reassign the :count users in this role before deleting it.', $this->role->users_count, ['count' => $this->role->users_count]) }}
                    </flux:subheading>
                @else
                    <flux:subheading>{{ __('This role has no users and can be deleted.') }}</flux:subheading>
                @endif
            </div>

            <flux:error name="delete" />

            <flux:modal.trigger name="confirm-role-deletion">
                <flux:button variant="danger" :disabled="$this->role->users_count > 0" data-test="delete-role-button">
                    {{ __('Delete role') }}
                </flux:button>
            </flux:modal.trigger>

            <flux:modal name="confirm-role-deletion" class="max-w-lg">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Delete the :name role?', ['name' => $this->role->name]) }}</flux:heading>
                        <flux:subheading>{{ __('This cannot be undone.') }}</flux:subheading>
                    </div>

                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>

                        <flux:button variant="danger" wire:click="delete" data-test="confirm-delete-role-button">
                            {{ __('Delete role') }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        </section>
    @endif
</section>
