<section class="w-full">
    <div class="mb-6">
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item :href="route('users.index')" wire:navigate>{{ __('Users') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $this->user ? $this->user->name : __('New user') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex items-center gap-3">
            <flux:heading size="xl" level="1">{{ $this->user ? __('Edit user') : __('New user') }}</flux:heading>

            @if ($this->user && ! $this->user->isActive())
                <flux:badge size="sm" color="zinc">{{ __('Deactivated') }}</flux:badge>
            @endif
        </div>

        <flux:subheading size="lg">
            {{ $this->user
                ? __('Update the account details and role')
                : __('The user will receive an email with a link to set their password') }}
        </flux:subheading>
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <form wire:submit="save" class="w-full max-w-lg space-y-6">
        <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="off" />

        <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="off" />

        @if ($this->canChangeRole)
            <flux:select wire:model="role" :label="__('Role')" :placeholder="__('Choose a role...')" required>
                @foreach ($this->assignableRoles as $roleOption)
                    <flux:select.option :value="$roleOption->name" wire:key="role-option-{{ $roleOption->id }}">{{ $roleOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @else
            <flux:input :value="$role !== '' ? $role : __('No role')" :label="__('Role')" disabled
                :description="__('You cannot change your own role.')" />
        @endif

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit" data-test="save-user-button">
                {{ $this->user ? __('Save') : __('Create user') }}
            </flux:button>

            <flux:button variant="ghost" :href="route('users.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>

    @if ($this->user)
        <section class="mt-12 max-w-lg space-y-4">
            <div>
                <flux:heading>{{ __('Password') }}</flux:heading>
                <flux:subheading>{{ __('Send the user a new link to set their password.') }}</flux:subheading>
            </div>

            <flux:button wire:click="sendPasswordLink" icon="envelope" data-test="send-password-link-button">
                {{ __('Send set-password link') }}
            </flux:button>
        </section>

        @can('deactivate', $this->user)
            <section class="mt-12 max-w-lg space-y-4">
                @if ($this->user->isActive())
                    <div>
                        <flux:heading>{{ __('Deactivate account') }}</flux:heading>
                        <flux:subheading>{{ __('The user will be signed out and can no longer sign in. Their history is kept.') }}</flux:subheading>
                    </div>

                    <flux:error name="deactivate" />

                    <flux:modal.trigger name="confirm-user-deactivation">
                        <flux:button variant="danger" data-test="deactivate-user-button">{{ __('Deactivate') }}</flux:button>
                    </flux:modal.trigger>

                    <flux:modal name="confirm-user-deactivation" class="max-w-lg">
                        <div class="space-y-6">
                            <div>
                                <flux:heading size="lg">{{ __('Deactivate :name?', ['name' => $this->user->name]) }}</flux:heading>
                                <flux:subheading>{{ __('You can reactivate the account later.') }}</flux:subheading>
                            </div>

                            <div class="flex justify-end gap-2">
                                <flux:modal.close>
                                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>

                                <flux:button variant="danger" wire:click="deactivate" data-test="confirm-deactivate-user-button">
                                    {{ __('Deactivate') }}
                                </flux:button>
                            </div>
                        </div>
                    </flux:modal>
                @else
                    <div>
                        <flux:heading>{{ __('Reactivate account') }}</flux:heading>
                        <flux:subheading>
                            {{ __('Deactivated :date. Reactivating lets the user sign in again.', ['date' => $this->user->deactivated_at?->toFormattedDayDateString()]) }}
                        </flux:subheading>
                    </div>

                    <flux:button variant="primary" wire:click="reactivate" data-test="reactivate-user-button">{{ __('Reactivate') }}</flux:button>
                @endif
            </section>
        @endcan
    @endif
</section>
