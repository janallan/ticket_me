<?php

namespace Tests\Feature\Tickets;

use App\Livewire\Tickets\TicketView;
use App\Models\Department;
use App\Notifications\TicketAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TicketAssignmentTest extends TestCase
{
    use InteractsWithTickets, RefreshDatabase;

    private Department $it;

    private Department $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTickets();

        [$this->it, $this->hr] = Department::factory()->count(2)->create();
    }

    public function test_workers_can_claim_an_unassigned_ticket(): void
    {
        Notification::fake();

        $agent = $this->agentIn($this->it);
        $ticket = $this->ticketIn($this->it, $this->requester());

        $this->actingAs($agent);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->call('claim')
            ->assertHasNoErrors();

        $this->assertTrue($ticket->refresh()->assignee->is($agent));

        Notification::assertNotSentTo($agent, TicketAssignedNotification::class);
    }

    public function test_an_assigned_ticket_cannot_be_claimed(): void
    {
        $assignee = $this->agentIn($this->it);
        $ticket = $this->ticketIn($this->it, $this->requester(), ['assignee_id' => $assignee->id]);

        $this->actingAs($this->agentIn($this->it));

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->call('claim')
            ->assertForbidden();

        $this->assertTrue($ticket->refresh()->assignee->is($assignee));
    }

    public function test_requesters_cannot_claim_their_own_ticket(): void
    {
        $requester = $this->requester($this->it);
        $ticket = $this->ticketIn($this->it, $requester);

        $this->actingAs($requester);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->call('claim')
            ->assertForbidden();
    }

    public function test_holders_of_tickets_all_can_assign_and_the_assignee_is_notified(): void
    {
        Notification::fake();

        $agent = $this->agentIn($this->it);
        $ticket = $this->ticketIn($this->it, $this->requester());

        $this->actingAs($this->manager());

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('assignForm.assignee', (string) $agent->id)
            ->call('assign')
            ->assertHasNoErrors();

        $this->assertTrue($ticket->refresh()->assignee->is($agent));

        Notification::assertSentTo($agent, TicketAssignedNotification::class,
            fn (TicketAssignedNotification $notification) => $notification->ticket->is($ticket));
    }

    public function test_a_ticket_can_be_unassigned(): void
    {
        $ticket = $this->ticketIn($this->it, $this->requester(), ['assignee_id' => $this->agentIn($this->it)->id]);

        $this->actingAs($this->manager());

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->assertSet('assignForm.assignee', (string) $ticket->assignee_id)
            ->set('assignForm.assignee', '')
            ->call('assign')
            ->assertHasNoErrors();

        $this->assertNull($ticket->refresh()->assignee_id);
    }

    public function test_agents_without_tickets_all_cannot_assign(): void
    {
        $agent = $this->agentIn($this->it);
        $ticket = $this->ticketIn($this->it, $this->requester());

        $this->actingAs($agent);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('assignForm.assignee', (string) $this->agentIn($this->it)->id)
            ->call('assign')
            ->assertForbidden();

        $this->assertNull($ticket->refresh()->assignee_id);
    }

    public function test_tickets_cannot_be_assigned_to_someone_who_cannot_work_them(): void
    {
        $ticket = $this->ticketIn($this->it, $this->requester());
        $deactivatedAgent = $this->agentIn($this->it);
        $deactivatedAgent->forceFill(['deactivated_at' => now()])->save();

        $this->actingAs($this->manager());

        foreach ([$this->agentIn($this->hr), $this->requester($this->it), $deactivatedAgent] as $ineligible) {
            Livewire::test(TicketView::class, ['ticket' => $ticket])
                ->set('assignForm.assignee', (string) $ineligible->id)
                ->call('assign')
                ->assertHasErrors(['assignForm.assignee']);
        }

        $this->assertNull($ticket->refresh()->assignee_id);
    }

    public function test_moving_a_ticket_to_another_department_drops_an_assignee_who_cannot_work_there(): void
    {
        $agent = $this->agentIn($this->it);
        $manager = $this->manager();
        $ticket = $this->ticketIn($this->it, $this->requester(), ['assignee_id' => $agent->id]);

        $this->actingAs($manager);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('detailsForm.department', (string) $this->hr->id)
            ->call('saveDetails')
            ->assertHasNoErrors();

        $ticket->refresh();

        $this->assertTrue($ticket->department->is($this->hr));
        $this->assertNull($ticket->assignee_id);
    }

    public function test_moving_a_ticket_keeps_an_assignee_who_can_work_in_the_new_department(): void
    {
        $manager = $this->manager();
        $ticket = $this->ticketIn($this->it, $this->requester(), ['assignee_id' => $manager->id]);

        $this->actingAs($manager);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('detailsForm.department', (string) $this->hr->id)
            ->call('saveDetails')
            ->assertHasNoErrors();

        $this->assertTrue($ticket->refresh()->assignee->is($manager));
    }
}
