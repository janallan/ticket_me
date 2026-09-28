<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketMessageType;
use App\Livewire\Tickets\TicketForm;
use App\Livewire\Tickets\TicketView;
use App\Models\Department;
use App\Models\TicketMessage;
use App\Models\TicketPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EditTicketTest extends TestCase
{
    use InteractsWithTickets, RefreshDatabase;

    private Department $it;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTickets();

        $this->it = Department::factory()->create(['name' => 'IT']);
    }

    public function test_the_edit_page_is_linked_from_the_ticket_and_filled_with_its_values(): void
    {
        $requester = $this->requester();
        $ticket = $this->ticketIn($this->it, $requester, ['subject' => 'Printer jammed', 'description' => 'Tray one']);

        $this->actingAs($requester);

        $this->get(route('tickets.show', $ticket))->assertSee(route('tickets.edit', $ticket));
        $this->get(route('tickets.edit', $ticket))->assertOk()->assertSee('Edit ticket '.$ticket->number.' - '.config('app.name'));

        Livewire::test(TicketForm::class, ['ticket' => $ticket])
            ->assertSet('ticketForm.subject', 'Printer jammed')
            ->assertSet('ticketForm.description', 'Tray one')
            ->assertSet('ticketForm.department', (string) $this->it->id)
            ->assertSet('ticketForm.priority', (string) $this->normalPriority->id)
            ->assertSet('ticketForm.status', (string) $this->openStatus->id);
    }

    public function test_the_requester_can_edit_an_open_ticket_and_the_original_values_are_logged(): void
    {
        $requester = $this->requester();
        $ticket = $this->ticketIn($this->it, $requester, ['subject' => 'Printer jammed', 'description' => 'Tray one']);
        $urgent = TicketPriority::factory()->create(['name' => 'Urgent']);

        $this->actingAs($requester);

        Livewire::test(TicketForm::class, ['ticket' => $ticket])
            ->set('ticketForm.subject', 'Printer on the 3rd floor is jammed')
            ->set('ticketForm.description', 'Tray three')
            ->set('ticketForm.priority', (string) $urgent->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();

        $this->assertSame('Printer on the 3rd floor is jammed', $ticket->subject);
        $this->assertSame('Tray three', $ticket->description);
        $this->assertTrue($ticket->priority->is($urgent));

        $log = $ticket->messages()->sole();

        $this->assertSame(TicketMessageType::Log, $log->type);
        $this->assertTrue($log->author->is($requester));
        $this->assertFalse($log->is_internal);
        $this->assertSame("Subject: Printer jammed\nDescription: Tray one\nPriority: Normal", $log->body);
    }

    public function test_saving_without_changes_adds_no_log(): void
    {
        $requester = $this->requester();
        $ticket = $this->ticketIn($this->it, $requester);

        $this->actingAs($requester);

        Livewire::test(TicketForm::class, ['ticket' => $ticket])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, $ticket->messages()->count());
    }

    public function test_an_edit_cannot_reuse_the_subject_of_the_requesters_other_open_ticket(): void
    {
        $requester = $this->requester();
        $this->ticketIn($this->it, $requester, ['subject' => 'Laptop will not boot']);
        $ticket = $this->ticketIn($this->it, $requester, ['subject' => 'Printer jammed']);

        $this->actingAs($requester);

        Livewire::test(TicketForm::class, ['ticket' => $ticket])
            ->set('ticketForm.subject', 'Laptop will not boot')
            ->call('save')
            ->assertHasErrors(['ticketForm.subject' => 'unique'])
            ->assertSee(__('You already have an open ticket with this subject.'));

        $this->assertSame('Printer jammed', $ticket->refresh()->subject);
    }

    public function test_the_subject_is_required(): void
    {
        $requester = $this->requester();
        $ticket = $this->ticketIn($this->it, $requester);

        $this->actingAs($requester);

        Livewire::test(TicketForm::class, ['ticket' => $ticket])
            ->set('ticketForm.subject', '')
            ->call('save')
            ->assertHasErrors(['ticketForm.subject' => 'required']);
    }

    public function test_a_requester_who_works_tickets_can_change_status_and_department_in_the_same_edit(): void
    {
        $requester = $this->manager();
        $hr = Department::factory()->create(['name' => 'HR']);
        $ticket = $this->ticketIn($this->it, $requester, ['subject' => 'Printer jammed']);

        $this->actingAs($requester);

        Livewire::test(TicketForm::class, ['ticket' => $ticket])
            ->set('ticketForm.subject', 'Printer on the 3rd floor is jammed')
            ->set('ticketForm.status', (string) $this->closedStatus->id)
            ->set('ticketForm.department', (string) $hr->id)
            ->call('save')
            ->assertHasNoErrors();

        $ticket->refresh();

        $this->assertTrue($ticket->status->is($this->closedStatus));
        $this->assertNotNull($ticket->closed_at);
        $this->assertTrue($ticket->department->is($hr));
        $this->assertSame("Subject: Printer jammed\nStatus: Open\nDepartment: IT", $ticket->messages()->sole()->body);
    }

    public function test_the_requester_can_change_the_department_but_not_the_status(): void
    {
        $requester = $this->requester();
        $hr = Department::factory()->create(['name' => 'HR']);
        $ticket = $this->ticketIn($this->it, $requester);

        $this->actingAs($requester);

        Livewire::test(TicketForm::class, ['ticket' => $ticket])
            ->set('ticketForm.status', (string) $this->closedStatus->id)
            ->call('save')
            ->assertHasErrors(['ticketForm.status']);

        Livewire::test(TicketForm::class, ['ticket' => $ticket])
            ->set('ticketForm.department', (string) $hr->id)
            ->call('save')
            ->assertHasNoErrors();

        $ticket->refresh();

        $this->assertTrue($ticket->status->is($this->openStatus));
        $this->assertTrue($ticket->department->is($hr));
    }

    public function test_tickets_cannot_be_moved_to_an_inactive_department(): void
    {
        $requester = $this->requester();
        $ticket = $this->ticketIn($this->it, $requester);

        $this->actingAs($requester);

        Livewire::test(TicketForm::class, ['ticket' => $ticket])
            ->set('ticketForm.department', (string) Department::factory()->inactive()->create()->id)
            ->call('save')
            ->assertHasErrors(['ticketForm.department' => 'in']);
    }

    public function test_the_requester_cannot_edit_a_closed_ticket(): void
    {
        $requester = $this->requester();
        $ticket = $this->ticketIn($this->it, $requester, ['ticket_status_id' => $this->closedStatus->id, 'closed_at' => now()]);

        $this->actingAs($requester);

        $this->get(route('tickets.show', $ticket))->assertDontSee(route('tickets.edit', $ticket));
        $this->get(route('tickets.edit', $ticket))->assertForbidden();
    }

    public function test_workers_cannot_edit_tickets_they_did_not_open(): void
    {
        $ticket = $this->ticketIn($this->it, $this->requester(), ['subject' => 'Printer jammed']);

        foreach ([$this->agentIn($this->it), $this->manager()] as $worker) {
            $this->actingAs($worker);

            $this->get(route('tickets.show', $ticket))->assertOk()->assertDontSee(route('tickets.edit', $ticket));
            $this->get(route('tickets.edit', $ticket))->assertForbidden();

            Livewire::test(TicketForm::class, ['ticket' => $ticket])->assertForbidden();
        }

        $this->assertSame('Printer jammed', $ticket->refresh()->subject);
    }

    public function test_other_users_cannot_edit_the_ticket(): void
    {
        $ticket = $this->ticketIn($this->it, $this->requester());

        $this->actingAs($this->agentIn(Department::factory()->create()))
            ->get(route('tickets.edit', $ticket))
            ->assertForbidden();

        $this->assertFalse($this->requester($this->it)->can('update', $ticket));
    }

    public function test_changing_the_details_logs_the_original_values(): void
    {
        $agent = $this->agentIn($this->it);
        $hr = Department::factory()->create(['name' => 'HR']);
        $urgent = TicketPriority::factory()->create(['name' => 'Urgent']);
        $ticket = $this->ticketIn($this->it, $this->requester(), ['assignee_id' => $agent->id]);

        $this->actingAs($this->manager());

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('detailsForm.status', (string) $this->closedStatus->id)
            ->set('detailsForm.priority', (string) $urgent->id)
            ->set('detailsForm.department', (string) $hr->id)
            ->call('saveDetails')
            ->assertHasNoErrors();

        $this->assertSame(
            "Status: Open\nPriority: Normal\nDepartment: IT\nAssignee: {$agent->name}",
            $ticket->messages()->sole()->body,
        );
    }

    public function test_assigning_logs_the_original_assignee(): void
    {
        $agent = $this->agentIn($this->it);
        $ticket = $this->ticketIn($this->it, $this->requester());

        $this->actingAs($this->manager());

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('assignForm.assignee', (string) $agent->id)
            ->call('assign')
            ->assertHasNoErrors();

        $this->assertSame('Assignee: '.__('Unassigned'), $ticket->messages()->sole()->body);
    }

    public function test_a_requester_reply_that_reopens_the_ticket_logs_the_original_status(): void
    {
        $requester = $this->requester();
        $ticket = $this->ticketIn($this->it, $requester, ['ticket_status_id' => $this->closedStatus->id, 'closed_at' => now()]);

        $this->actingAs($requester);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('replyForm.body', 'It is broken again.')
            ->call('reply')
            ->assertHasNoErrors();

        $log = $ticket->messages()->where('type', TicketMessageType::Log)->sole();

        $this->assertSame('Status: Resolved', $log->body);
    }

    public function test_the_requester_sees_log_entries(): void
    {
        $requester = $this->requester();
        $ticket = $this->ticketIn($this->it, $requester);

        TicketMessage::factory()->for($ticket)->create(['type' => TicketMessageType::Log, 'body' => 'Priority: Low']);

        $this->actingAs($requester)
            ->get(route('tickets.show', $ticket))
            ->assertSee(__('Changes (original values)'))
            ->assertSee('Priority: Low');
    }
}
