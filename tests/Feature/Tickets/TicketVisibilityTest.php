<?php

namespace Tests\Feature\Tickets;

use App\Livewire\Tickets\TicketList;
use App\Models\Department;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TicketVisibilityTest extends TestCase
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

    public function test_guests_are_redirected_to_login(): void
    {
        $ticket = $this->ticketIn($this->it, $this->requester());

        $this->get(route('tickets.index'))->assertRedirect(route('login'));
        $this->get(route('tickets.show', $ticket))->assertRedirect(route('login'));
    }

    public function test_the_requester_can_view_their_own_ticket(): void
    {
        $requester = $this->requester($this->hr);
        $ticket = $this->ticketIn($this->it, $requester, ['subject' => 'Printer is jammed']);

        $this->actingAs($requester);

        $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('Printer is jammed');
        $this->get(route('tickets.index'))->assertOk()->assertSee('Printer is jammed');
    }

    public function test_agents_see_tickets_in_their_departments_only(): void
    {
        $itTicket = $this->ticketIn($this->it, $this->requester(), ['subject' => 'Laptop will not boot']);
        $hrTicket = $this->ticketIn($this->hr, $this->requester(), ['subject' => 'Payslip is missing']);

        $this->actingAs($this->agentIn($this->it));

        $this->get(route('tickets.show', $itTicket))->assertOk();
        $this->get(route('tickets.show', $hrTicket))->assertForbidden();

        Livewire::test(TicketList::class)
            ->assertSee('Laptop will not boot')
            ->assertDontSee('Payslip is missing');
    }

    public function test_users_without_ticket_permissions_cannot_see_other_peoples_tickets(): void
    {
        $ticket = $this->ticketIn($this->it, $this->requester(), ['subject' => 'Laptop will not boot']);

        $this->actingAs($this->requester($this->it));

        $this->get(route('tickets.show', $ticket))->assertForbidden();

        Livewire::test(TicketList::class)->assertDontSee('Laptop will not boot');
    }

    public function test_holders_of_tickets_all_see_every_ticket(): void
    {
        $itTicket = $this->ticketIn($this->it, $this->requester(), ['subject' => 'Laptop will not boot']);
        $hrTicket = $this->ticketIn($this->hr, $this->requester(), ['subject' => 'Payslip is missing']);

        $this->actingAs($this->manager());

        $this->get(route('tickets.show', $itTicket))->assertOk();
        $this->get(route('tickets.show', $hrTicket))->assertOk();

        Livewire::test(TicketList::class)
            ->assertSee('Laptop will not boot')
            ->assertSee('Payslip is missing');
    }

    public function test_deactivated_agents_cannot_view_tickets(): void
    {
        $ticket = $this->ticketIn($this->it, $this->requester());
        $agent = $this->agentIn($this->it);
        $agent->forceFill(['deactivated_at' => now()])->save();

        $this->assertFalse($agent->can('view', $ticket));
    }

    public function test_internal_notes_are_hidden_from_the_requester(): void
    {
        $requester = $this->requester();
        $agent = $this->agentIn($this->it);
        $ticket = $this->ticketIn($this->it, $requester);

        TicketMessage::factory()->for($ticket)->create(['user_id' => $agent->id, 'body' => 'Public answer']);
        TicketMessage::factory()->internal()->for($ticket)->create(['user_id' => $agent->id, 'body' => 'Secret note']);

        $this->actingAs($requester)
            ->get(route('tickets.show', $ticket))
            ->assertSee('Public answer')
            ->assertDontSee('Secret note');

        $this->actingAs($agent)
            ->get(route('tickets.show', $ticket))
            ->assertSee('Public answer')
            ->assertSee('Secret note');
    }

    public function test_attachments_on_internal_notes_can_only_be_downloaded_by_workers(): void
    {
        Storage::fake('local');

        $requester = $this->requester();
        $agent = $this->agentIn($this->it);
        $ticket = $this->ticketIn($this->it, $requester);

        $note = TicketMessage::factory()->internal()->for($ticket)->create(['user_id' => $agent->id]);
        $noteFile = TicketAttachment::factory()->forMessage($note)->create(['path' => 'tickets/note.pdf', 'original_name' => 'note.pdf']);
        $descriptionFile = TicketAttachment::factory()->for($ticket)->create(['path' => 'tickets/screenshot.pdf', 'original_name' => 'screenshot.pdf']);
        Storage::disk('local')->put('tickets/note.pdf', 'note');
        Storage::disk('local')->put('tickets/screenshot.pdf', 'screenshot');

        $this->actingAs($requester);
        $this->get(route('tickets.attachments.show', $noteFile))->assertForbidden();
        $this->get(route('tickets.attachments.show', $descriptionFile))->assertOk()->assertDownload('screenshot.pdf');

        $this->actingAs($agent);
        $this->get(route('tickets.attachments.show', $noteFile))->assertOk()->assertDownload('note.pdf');
    }

    public function test_other_users_cannot_download_a_tickets_attachments(): void
    {
        $ticket = $this->ticketIn($this->it, $this->requester());
        $file = TicketAttachment::factory()->for($ticket)->create();

        $this->actingAs($this->agentIn($this->hr))
            ->get(route('tickets.attachments.show', $file))
            ->assertForbidden();
    }

    public function test_the_list_can_be_searched_by_number_subject_and_description(): void
    {
        $requester = $this->requester();
        $printer = $this->ticketIn($this->it, $requester, ['subject' => 'Printer is jammed', 'description' => 'Paper tray three']);
        $this->ticketIn($this->it, $requester, ['subject' => 'Laptop will not boot', 'description' => 'Black screen']);

        $this->actingAs($requester);

        Livewire::test(TicketList::class)
            ->set('search', 'tray three')
            ->assertSee('Printer is jammed')
            ->assertDontSee('Laptop will not boot')
            ->set('search', 'Laptop')
            ->assertSee('Laptop will not boot')
            ->assertDontSee('Printer is jammed')
            ->set('search', $printer->number)
            ->assertSee('Printer is jammed')
            ->assertDontSee('Laptop will not boot');
    }

    public function test_closed_tickets_are_hidden_unless_show_closed_is_on(): void
    {
        $requester = $this->requester();
        $this->ticketIn($this->it, $requester, ['subject' => 'Still broken']);
        $this->ticketIn($this->it, $requester, ['subject' => 'Already fixed', 'ticket_status_id' => $this->closedStatus->id, 'closed_at' => now()]);

        $this->actingAs($requester);

        Livewire::test(TicketList::class)
            ->assertSee('Still broken')
            ->assertDontSee('Already fixed')
            ->set('showClosed', true)
            ->assertSee('Already fixed');
    }

    public function test_the_assignment_switches_filter_the_list_and_exclude_each_other(): void
    {
        $agent = $this->agentIn($this->it);
        $mine = $this->ticketIn($this->it, $this->requester(), ['subject' => 'Assigned to the agent', 'assignee_id' => $agent->id]);
        $this->ticketIn($this->it, $this->requester(), ['subject' => 'Nobody has it']);

        $this->actingAs($agent);

        Livewire::test(TicketList::class)
            ->assertSee(__('Assigned to me'))
            ->set('assignedToMe', true)
            ->assertSee($mine->subject)
            ->assertDontSee('Nobody has it')
            ->set('unassigned', true)
            ->assertSet('assignedToMe', false)
            ->assertSee('Nobody has it')
            ->assertDontSee($mine->subject)
            ->set('unassigned', false)
            ->assertSee($mine->subject)
            ->assertSee('Nobody has it');
    }

    public function test_the_assignment_switches_are_only_offered_to_workers(): void
    {
        $requester = $this->requester();
        $this->ticketIn($this->it, $requester, ['subject' => 'Printer is jammed', 'assignee_id' => $this->agentIn($this->it)->id]);

        $this->actingAs($requester);

        Livewire::test(TicketList::class)
            ->assertSee(__('Opened by me'))
            ->assertDontSee(__('Assigned to me'))
            ->set('unassigned', true)
            ->assertSee('Printer is jammed');
    }

    public function test_the_opened_by_me_switch_shows_only_the_users_own_tickets(): void
    {
        $agent = $this->agentIn($this->it);
        $this->ticketIn($this->it, $agent, ['subject' => 'My own laptop']);
        $this->ticketIn($this->it, $this->requester(), ['subject' => 'Someone elses printer']);

        $this->actingAs($agent);

        Livewire::test(TicketList::class)
            ->assertSee('Someone elses printer')
            ->set('openedByMe', true)
            ->assertSee('My own laptop')
            ->assertDontSee('Someone elses printer');
    }

    public function test_each_ticket_in_the_list_links_to_its_page(): void
    {
        $requester = $this->requester();
        $ticket = $this->ticketIn($this->it, $requester);

        $this->actingAs($requester);

        Livewire::test(TicketList::class)
            ->assertSeeHtml('href="'.route('tickets.show', $ticket).'"')
            ->assertSee(__('View'));
    }
}
