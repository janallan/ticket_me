<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>

                @can('viewAny', App\Models\Ticket::class)
                    <flux:navbar.item icon="inbox" :href="route('tickets.index')" :current="request()->routeIs('tickets.*')" wire:navigate>
                        {{ __('Tickets') }}
                    </flux:navbar.item>
                @endcan

                @can('viewAny', App\Models\User::class)
                    <flux:navbar.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>
                        {{ __('Users') }}
                    </flux:navbar.item>
                @endcan

                @can('viewAny', Spatie\Permission\Models\Role::class)
                    <flux:navbar.item icon="shield-check" :href="route('roles.index')" :current="request()->routeIs('roles.*')" wire:navigate>
                        {{ __('Roles') }}
                    </flux:navbar.item>
                @endcan

                @if (auth()->user()->can('viewAny', App\Models\Department::class)
                    || auth()->user()->can('viewAny', App\Models\TicketStatus::class)
                    || auth()->user()->can('viewAny', App\Models\TicketPriority::class))
                    <flux:dropdown>
                        <flux:navbar.item icon="cog-6-tooth" icon:trailing="chevron-down"
                            :current="request()->routeIs('departments.*', 'ticket-statuses.*', 'ticket-priorities.*')">
                            {{ __('Ticket settings') }}
                        </flux:navbar.item>

                        <flux:navmenu>
                            @can('viewAny', App\Models\Department::class)
                                <flux:navmenu.item icon="building-office" :href="route('departments.index')" wire:navigate>{{ __('Departments') }}</flux:navmenu.item>
                            @endcan
                            @can('viewAny', App\Models\TicketStatus::class)
                                <flux:navmenu.item icon="tag" :href="route('ticket-statuses.index')" wire:navigate>{{ __('Statuses') }}</flux:navmenu.item>
                            @endcan
                            @can('viewAny', App\Models\TicketPriority::class)
                                <flux:navmenu.item icon="flag" :href="route('ticket-priorities.index')" wire:navigate>{{ __('Priorities') }}</flux:navmenu.item>
                            @endcan
                        </flux:navmenu>
                    </flux:dropdown>
                @endif
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
                <flux:tooltip :content="__('Search')" position="bottom">
                    <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#" :label="__('Search')" />
                </flux:tooltip>
            </flux:navbar>

            <x-desktop-user-menu />
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')">
                    <flux:sidebar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard')  }}
                    </flux:sidebar.item>

                    @can('viewAny', App\Models\Ticket::class)
                        <flux:sidebar.item icon="inbox" :href="route('tickets.index')" :current="request()->routeIs('tickets.*')" wire:navigate>
                            {{ __('Tickets') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                @include('partials.admin-nav')
            </flux:sidebar.nav>
        </flux:sidebar>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
