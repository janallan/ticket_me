<?php

namespace Tests\Feature\Tickets;

use App\Livewire\Tickets\TicketView;
use App\Models\Department;
use App\Models\TicketPriority;
use App\Notifications\TicketStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TicketStatusChangeTest extends TestCase
{
    use InteractsWithTickets, RefreshDatabase;

    private Department $it;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTickets();

        $this->it = Department::factory()->create();
    }

    public function test_closing_a_ticket_sets_closed_at_and_notifies_the_requester(): void
    {
        Notification::fake();
        $this->freezeSecond();

        $requester = $this->requester();
        $agent = $this->agentIn($this->it);
        $ticket = $this->ticketIn($this->it, $requester);

        $this->actingAs($agent);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('detailsForm.status', (string) $this->closedStatus->id)
            ->call('saveDetails')
            ->assertHasNoErrors();

        $ticket->refresh();

        $this->assertTrue($ticket->status->is($this->closedStatus));
        $this->assertTrue($ticket->closed_at->equalTo(now()));

        Notification::assertSentTo($requester, TicketStatusChangedNotification::class,
            fn (TicketStatusChangedNotification $notification) => $notification->previousStatus->is($this->openStatus));
        Notification::assertNotSentTo($agent, TicketStatusChangedNotification::class);
    }

    public function test_reopening_a_ticket_clears_closed_at(): void
    {
        $ticket = $this->ticketIn($this->it, $this->requester(), ['ticket_status_id' => $this->closedStatus->id, 'closed_at' => now()]);

        $this->actingAs($this->agentIn($this->it));

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('detailsForm.status', (string) $this->openStatus->id)
            ->call('saveDetails')
            ->assertHasNoErrors();

        $this->assertNull($ticket->refresh()->closed_at);
    }

    public function test_changing_only_the_priority_does_not_notify_the_requester(): void
    {
        Notification::fake();

        $requester = $this->requester();
        $ticket = $this->ticketIn($this->it, $requester);
        $urgent = TicketPriority::factory()->create(['name' => 'Urgent']);

        $this->actingAs($this->agentIn($this->it));

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('detailsForm.priority', (string) $urgent->id)
            ->call('saveDetails')
            ->assertHasNoErrors();

        $this->assertTrue($ticket->refresh()->priority->is($urgent));

        Notification::assertNothingSentTo($requester);
    }

    public function test_requesters_cannot_change_the_details(): void
    {
        $requester = $this->requester($this->it);
        $ticket = $this->ticketIn($this->it, $requester);

        $this->actingAs($requester);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('detailsForm.status', (string) $this->closedStatus->id)
            ->call('saveDetails')
            ->assertForbidden();

        $this->assertTrue($ticket->refresh()->status->is($this->openStatus));
    }

    public function test_moving_a_ticket_out_of_the_agents_departments_returns_them_to_the_list(): void
    {
        $ticket = $this->ticketIn($this->it, $this->requester());

        $this->actingAs($this->agentIn($this->it));

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('detailsForm.department', (string) Department::factory()->create()->id)
            ->call('saveDetails')
            ->assertRedirect(route('tickets.index'));
    }
}
