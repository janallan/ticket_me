<?php

namespace Tests\Feature\Tickets;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Shared setup for ticket tests: the seeded roles, and a default open status, a closed status
 * and a default priority.
 */
trait InteractsWithTickets
{
    protected TicketStatus $openStatus;

    protected TicketStatus $closedStatus;

    protected TicketPriority $normalPriority;

    protected function setUpTickets(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->openStatus = TicketStatus::factory()->default()->create(['name' => 'Open']);
        $this->closedStatus = TicketStatus::factory()->closed()->create(['name' => 'Resolved']);
        $this->normalPriority = TicketPriority::factory()->default()->create(['name' => 'Normal']);
    }

    /**
     * A user with no ticket permissions, who can only open and follow their own tickets.
     */
    protected function requester(?Department $department = null): User
    {
        return User::factory()->inDepartment($department)->create();
    }

    /**
     * A user with the Agent role (`tickets`), who works tickets in the given department.
     */
    protected function agentIn(Department $department): User
    {
        return User::factory()->inDepartment($department)->create()->assignRole('Agent');
    }

    /**
     * A user with the Manager role (`tickets` and `tickets-all`).
     */
    protected function manager(?Department $department = null): User
    {
        return User::factory()->inDepartment($department)->create()->assignRole('Manager');
    }

    /**
     * An open ticket in the department, opened by the requester.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function ticketIn(Department $department, User $requester, array $attributes = []): Ticket
    {
        return Ticket::factory()->create([
            'department_id' => $department->id,
            'requester_id' => $requester->id,
            'ticket_status_id' => $this->openStatus->id,
            'ticket_priority_id' => $this->normalPriority->id,
            ...$attributes,
        ]);
    }
}
