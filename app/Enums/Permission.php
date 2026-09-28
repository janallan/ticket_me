<?php

namespace App\Enums;

/**
 * Permissions enforced by the application. Each permission grants full access to its area.
 */
enum Permission: string
{
    case Users = 'users';
    case Roles = 'roles';
    case Tickets = 'tickets';
    case TicketsAll = 'tickets-all';
    case Departments = 'departments';
    case TicketStatuses = 'ticket-statuses';
    case TicketPriorities = 'ticket-priorities';

    /**
     * Get the human-readable name of the permission.
     */
    public function label(): string
    {
        return match ($this) {
            self::Users => __('Users'),
            self::Roles => __('Roles'),
            self::Tickets => __('Tickets'),
            self::TicketsAll => __('All tickets'),
            self::Departments => __('Departments'),
            self::TicketStatuses => __('Ticket statuses'),
            self::TicketPriorities => __('Ticket priorities'),
        };
    }

    /**
     * Get a short description of what the permission grants.
     */
    public function description(): string
    {
        return match ($this) {
            self::Users => __('View, add, edit and deactivate users.'),
            self::Roles => __('Manage roles and their permissions.'),
            self::Tickets => __('Work tickets in your departments: reply, add internal notes, claim, and change status, priority and department.'),
            self::TicketsAll => __('See and work every ticket, and assign tickets to anyone.'),
            self::Departments => __('Manage departments and their members.'),
            self::TicketStatuses => __('Manage ticket statuses.'),
            self::TicketPriorities => __('Manage ticket priorities.'),
        };
    }
}
