{{-- Administration and ticket settings groups, shared by the sidebar and the mobile menu. --}}
@php
    $canManageUsers = auth()->user()->can('viewAny', App\Models\User::class);
    $canManageRoles = auth()->user()->can('viewAny', Spatie\Permission\Models\Role::class);
    $canManageDepartments = auth()->user()->can('viewAny', App\Models\Department::class);
    $canManageTicketStatuses = auth()->user()->can('viewAny', App\Models\TicketStatus::class);
    $canManageTicketPriorities = auth()->user()->can('viewAny', App\Models\TicketPriority::class);
@endphp

@if ($canManageUsers || $canManageRoles)
    <flux:sidebar.group :heading="__('Administration')" class="grid mt-3">
        @if ($canManageUsers)
            <flux:sidebar.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>
                {{ __('Users') }}
            </flux:sidebar.item>
        @endif

        @if ($canManageRoles)
            <flux:sidebar.item icon="shield-check" :href="route('roles.index')" :current="request()->routeIs('roles.*')" wire:navigate>
                {{ __('Roles') }}
            </flux:sidebar.item>
        @endif
    </flux:sidebar.group>
@endif

@if ($canManageDepartments || $canManageTicketStatuses || $canManageTicketPriorities)
    <flux:sidebar.group :heading="__('Ticket settings')" class="grid mt-3">
        @if ($canManageDepartments)
            <flux:sidebar.item icon="building-office" :href="route('departments.index')" :current="request()->routeIs('departments.*')" wire:navigate>
                {{ __('Departments') }}
            </flux:sidebar.item>
        @endif

        @if ($canManageTicketStatuses)
            <flux:sidebar.item icon="tag" :href="route('ticket-statuses.index')" :current="request()->routeIs('ticket-statuses.*')" wire:navigate>
                {{ __('Statuses') }}
            </flux:sidebar.item>
        @endif

        @if ($canManageTicketPriorities)
            <flux:sidebar.item icon="flag" :href="route('ticket-priorities.index')" :current="request()->routeIs('ticket-priorities.*')" wire:navigate>
                {{ __('Priorities') }}
            </flux:sidebar.item>
        @endif
    </flux:sidebar.group>
@endif
