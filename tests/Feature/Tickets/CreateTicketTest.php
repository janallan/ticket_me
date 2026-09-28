<?php

namespace Tests\Feature\Tickets;

use App\Enums\Permission;
use App\Livewire\Tickets\TicketForm;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Notifications\TicketCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CreateTicketTest extends TestCase
{
    use InteractsWithTickets, RefreshDatabase;

    private Department $it;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTickets();

        $this->it = Department::factory()->create(['name' => 'IT']);
    }

    public function test_users_without_any_permission_can_open_tickets(): void
    {
        $requester = $this->requester($this->it);

        $this->assertCount(0, $requester->getAllPermissions());

        $this->actingAs($requester)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('New ticket - '.config('app.name'));
    }

    public function test_the_form_starts_on_the_requesters_default_department_and_the_default_priority(): void
    {
        $this->actingAs($this->requester($this->it));

        Livewire::test(TicketForm::class)
            ->assertSet('ticketForm.department', (string) $this->it->id)
            ->assertSet('ticketForm.priority', (string) $this->normalPriority->id);
    }

    public function test_an_inactive_default_department_is_not_preselected(): void
    {
        $this->actingAs($this->requester(Department::factory()->inactive()->create()));

        Livewire::test(TicketForm::class)->assertSet('ticketForm.department', '');
    }

    public function test_a_ticket_is_opened_with_its_description_and_attachments(): void
    {
        Storage::fake('local');
        Notification::fake();

        $this->actingAs($requester = $this->requester($this->it));

        $high = TicketPriority::factory()->create(['name' => 'High']);

        Livewire::test(TicketForm::class)
            ->set('ticketForm.subject', 'Printer is jammed')
            ->set('ticketForm.priority', (string) $high->id)
            ->set('ticketForm.description', 'Paper is stuck in tray three.')
            ->set('ticketForm.attachments', [UploadedFile::fake()->create('photo.pdf', 200, 'application/pdf')])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('tickets.show', Ticket::firstOrFail()));

        $ticket = Ticket::firstOrFail();

        $this->assertSame('Printer is jammed', $ticket->subject);
        $this->assertSame('Paper is stuck in tray three.', $ticket->description);
        $this->assertTrue($ticket->department->is($this->it));
        $this->assertTrue($ticket->status->is($this->openStatus));
        $this->assertTrue($ticket->priority->is($high));
        $this->assertTrue($ticket->requester->is($requester));
        $this->assertNull($ticket->assignee_id);
        $this->assertNull($ticket->closed_at);
        $this->assertCount(0, $ticket->messages);

        $attachment = $ticket->attachments()->sole();
        $this->assertSame('photo.pdf', $attachment->original_name);
        $this->assertStringStartsWith('tickets/'.$ticket->id.'/', $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_opening_a_ticket_notifies_the_departments_active_workers(): void
    {
        Notification::fake();

        $agent = $this->agentIn($this->it);
        $deactivatedAgent = $this->agentIn($this->it);
        $deactivatedAgent->forceFill(['deactivated_at' => now()])->save();
        $otherDepartmentAgent = $this->agentIn(Department::factory()->create());
        $memberWithoutPermission = $this->requester($this->it);

        $this->actingAs($requester = $this->requester($this->it));

        Livewire::test(TicketForm::class)
            ->set('ticketForm.subject', 'Printer is jammed')
            ->set('ticketForm.description', 'Paper is stuck.')
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertSentTo($agent, TicketCreatedNotification::class,
            fn (TicketCreatedNotification $notification) => $notification->ticket->is(Ticket::firstOrFail()));
        Notification::assertNotSentTo([$deactivatedAgent, $otherDepartmentAgent, $memberWithoutPermission, $requester], TicketCreatedNotification::class);
    }

    public function test_a_ticket_can_be_opened_before_every_permission_is_synced(): void
    {
        Notification::fake();

        $agent = $this->agentIn($this->it);
        PermissionModel::findByName(Permission::TicketsAll->value)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->requester($this->it));

        Livewire::test(TicketForm::class)
            ->set('ticketForm.subject', 'Printer is jammed')
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertSentTo($agent, TicketCreatedNotification::class);
    }

    public function test_the_workers_own_ticket_does_not_notify_them(): void
    {
        Notification::fake();

        $this->actingAs($agent = $this->agentIn($this->it));

        Livewire::test(TicketForm::class)
            ->set('ticketForm.subject', 'My monitor flickers')
            ->set('ticketForm.description', 'Since this morning.')
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertNotSentTo($agent, TicketCreatedNotification::class);
    }

    public function test_workers_can_open_a_ticket_in_another_status(): void
    {
        $pending = TicketStatus::factory()->create(['name' => 'Pending']);

        $this->actingAs($this->agentIn($this->it));

        Livewire::test(TicketForm::class)
            ->assertSet('ticketForm.status', (string) $this->openStatus->id)
            ->set('ticketForm.subject', 'Scheduled maintenance')
            ->set('ticketForm.description', 'Server patching on Friday.')
            ->set('ticketForm.status', (string) $pending->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Ticket::firstOrFail()->status->is($pending));
    }

    public function test_requesters_can_only_open_tickets_in_the_default_status(): void
    {
        $this->actingAs($this->requester($this->it));

        Livewire::test(TicketForm::class)
            ->set('ticketForm.subject', 'Printer is jammed')
            ->set('ticketForm.description', 'Paper is stuck.')
            ->set('ticketForm.status', (string) $this->closedStatus->id)
            ->call('save')
            ->assertHasErrors(['ticketForm.status']);

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_agents_cannot_pick_a_status_for_a_department_they_do_not_work_in(): void
    {
        $this->actingAs($this->agentIn(Department::factory()->create()));

        Livewire::test(TicketForm::class)
            ->set('ticketForm.subject', 'Printer is jammed')
            ->set('ticketForm.department', (string) $this->it->id)
            ->set('ticketForm.description', 'Paper is stuck.')
            ->set('ticketForm.status', (string) $this->closedStatus->id)
            ->call('save')
            ->assertHasErrors(['ticketForm.status']);
    }

    public function test_a_ticket_can_be_opened_without_a_description(): void
    {
        Notification::fake();

        $this->actingAs($this->requester($this->it));
        $agent = $this->agentIn($this->it);

        Livewire::test(TicketForm::class)
            ->set('ticketForm.subject', 'Please call me back')
            ->call('save')
            ->assertHasNoErrors();

        $ticket = Ticket::firstOrFail();

        $this->assertNull($ticket->description);
        $this->get(route('tickets.show', $ticket))->assertOk()->assertSee(__('No description.'));

        Notification::assertSentTo($agent, TicketCreatedNotification::class,
            fn (TicketCreatedNotification $notification) => str_contains((string) $notification->toMail($agent)->render(), __('View ticket')));
    }

    public function test_the_subject_cannot_match_one_of_your_open_tickets(): void
    {
        $requester = $this->requester($this->it);
        $this->ticketIn($this->it, $requester, ['subject' => 'Printer is jammed']);

        $this->actingAs($requester);

        Livewire::test(TicketForm::class)
            ->set('ticketForm.subject', 'Printer is jammed')
            ->call('save')
            ->assertHasErrors(['ticketForm.subject' => 'unique'])
            ->assertSee(__('You already have an open ticket with this subject.'));

        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_the_subject_may_match_a_closed_ticket_or_someone_elses_ticket(): void
    {
        $requester = $this->requester($this->it);
        $this->ticketIn($this->it, $requester, ['subject' => 'Printer is jammed', 'ticket_status_id' => $this->closedStatus->id, 'closed_at' => now()]);
        $this->ticketIn($this->it, $this->requester(), ['subject' => 'Laptop will not boot']);

        $this->actingAs($requester);

        foreach (['Printer is jammed', 'Laptop will not boot'] as $subject) {
            Livewire::test(TicketForm::class)
                ->set('ticketForm.subject', $subject)
                ->call('save')
                ->assertHasNoErrors();
        }
    }

    public function test_subject_and_department_are_required(): void
    {
        $this->actingAs($this->requester());

        Livewire::test(TicketForm::class)
            ->set('ticketForm.department', '')
            ->call('save')
            ->assertHasErrors(['ticketForm.subject' => 'required', 'ticketForm.department' => 'required'])
            ->assertSee(__('validation.required', ['attribute' => __('subject')]));

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_tickets_cannot_be_opened_in_an_inactive_department(): void
    {
        $this->actingAs($this->requester($this->it));

        Livewire::test(TicketForm::class)
            ->set('ticketForm.subject', 'Printer is jammed')
            ->set('ticketForm.department', (string) Department::factory()->inactive()->create()->id)
            ->set('ticketForm.description', 'Paper is stuck.')
            ->call('save')
            ->assertHasErrors(['ticketForm.department' => 'in']);
    }

    public function test_attachments_must_respect_the_count_and_type_limits(): void
    {
        Storage::fake('local');

        $this->actingAs($this->requester($this->it));

        $tooMany = collect(range(1, 6))->map(fn (int $index) => UploadedFile::fake()->create("file{$index}.pdf", 10, 'application/pdf'))->all();

        Livewire::test(TicketForm::class)
            ->set('ticketForm.subject', 'Printer is jammed')
            ->set('ticketForm.description', 'Paper is stuck.')
            ->set('ticketForm.attachments', $tooMany)
            ->call('save')
            ->assertHasErrors(['ticketForm.attachments' => 'max']);

        Livewire::test(TicketForm::class)
            ->set('ticketForm.subject', 'Printer is jammed')
            ->set('ticketForm.description', 'Paper is stuck.')
            ->set('ticketForm.attachments', [UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload')])
            ->call('save')
            ->assertHasErrors(['ticketForm.attachments.0' => 'mimes']);

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_tickets_cannot_be_opened_without_a_default_status(): void
    {
        TicketStatus::query()->update(['is_default' => false]);

        $this->actingAs($this->requester($this->it));

        Livewire::test(TicketForm::class)
            ->set('ticketForm.subject', 'Printer is jammed')
            ->set('ticketForm.description', 'Paper is stuck.')
            ->call('save')
            ->assertHasErrors(['ticketForm.status' => 'required']);

        $this->assertDatabaseCount('tickets', 0);
    }
}
