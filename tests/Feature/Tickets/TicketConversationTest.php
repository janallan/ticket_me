<?php

namespace Tests\Feature\Tickets;

use App\Livewire\Tickets\TicketView;
use App\Models\Department;
use App\Models\TicketMessage;
use App\Notifications\TicketNoteAddedNotification;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TicketConversationTest extends TestCase
{
    use InteractsWithTickets, RefreshDatabase;

    private Department $it;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTickets();

        $this->it = Department::factory()->create();
    }

    public function test_a_reply_is_saved_with_attachments_and_bumps_the_last_activity(): void
    {
        Storage::fake('local');
        Notification::fake();
        $this->freezeSecond();

        $requester = $this->requester();
        $ticket = $this->ticketIn($this->it, $requester, ['last_activity_at' => now()->subDay()]);

        $this->actingAs($requester);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('replyForm.body', 'Any update?')
            ->set('replyForm.attachments', [UploadedFile::fake()->create('log.txt', 5, 'text/plain')])
            ->call('reply')
            ->assertHasNoErrors()
            ->assertSet('replyForm.body', '');

        $message = $ticket->messages()->sole();

        $this->assertSame('Any update?', $message->body);
        $this->assertFalse($message->is_internal);
        $this->assertSame('log.txt', $message->attachments()->sole()->original_name);
        $this->assertTrue($ticket->refresh()->last_activity_at->equalTo(now()));
    }

    public function test_a_requester_reply_notifies_the_assignee(): void
    {
        Notification::fake();

        $requester = $this->requester();
        $assignee = $this->agentIn($this->it);
        $otherAgent = $this->agentIn($this->it);
        $ticket = $this->ticketIn($this->it, $requester, ['assignee_id' => $assignee->id]);

        $this->actingAs($requester);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('replyForm.body', 'Any update?')
            ->call('reply')
            ->assertHasNoErrors();

        Notification::assertSentTo($assignee, TicketRepliedNotification::class);
        Notification::assertNotSentTo([$requester, $otherAgent], TicketRepliedNotification::class);
    }

    public function test_a_reply_on_an_unassigned_ticket_notifies_the_departments_workers(): void
    {
        Notification::fake();

        $requester = $this->requester();
        $agent = $this->agentIn($this->it);
        $ticket = $this->ticketIn($this->it, $requester);

        $this->actingAs($requester);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('replyForm.body', 'Any update?')
            ->call('reply');

        Notification::assertSentTo($agent, TicketRepliedNotification::class);
    }

    public function test_a_worker_reply_notifies_the_requester(): void
    {
        Notification::fake();

        $requester = $this->requester();
        $agent = $this->agentIn($this->it);
        $ticket = $this->ticketIn($this->it, $requester, ['assignee_id' => $agent->id]);

        $this->actingAs($agent);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('replyForm.body', 'On my way.')
            ->call('reply')
            ->assertHasNoErrors();

        Notification::assertSentTo($requester, TicketRepliedNotification::class);
        Notification::assertNotSentTo($agent, TicketRepliedNotification::class);
    }

    public function test_workers_can_add_internal_notes_that_notify_only_the_assignee(): void
    {
        Notification::fake();

        $requester = $this->requester();
        $assignee = $this->agentIn($this->it);
        $ticket = $this->ticketIn($this->it, $requester, ['assignee_id' => $assignee->id]);

        $this->actingAs($this->agentIn($this->it));

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('replyForm.body', 'Checked the logs, looks like hardware.')
            ->set('replyForm.isInternal', true)
            ->call('reply')
            ->assertHasNoErrors();

        $this->assertTrue($ticket->messages()->sole()->is_internal);

        Notification::assertSentTo($assignee, TicketNoteAddedNotification::class);
        Notification::assertNotSentTo($requester, TicketNoteAddedNotification::class);
        Notification::assertNotSentTo($requester, TicketRepliedNotification::class);
    }

    public function test_requesters_cannot_add_internal_notes(): void
    {
        $requester = $this->requester();
        $ticket = $this->ticketIn($this->it, $requester);

        $this->actingAs($requester);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('replyForm.body', 'Sneaky note')
            ->set('replyForm.isInternal', true)
            ->call('reply')
            ->assertForbidden();

        $this->assertSame(0, $ticket->messages()->count());
    }

    public function test_a_requester_reply_reopens_a_closed_ticket(): void
    {
        $requester = $this->requester();
        $ticket = $this->ticketIn($this->it, $requester, ['ticket_status_id' => $this->closedStatus->id, 'closed_at' => now()]);

        $this->actingAs($requester);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('replyForm.body', 'It is broken again.')
            ->call('reply')
            ->assertHasNoErrors();

        $ticket->refresh();

        $this->assertTrue($ticket->status->is($this->openStatus));
        $this->assertNull($ticket->closed_at);
    }

    public function test_a_worker_reply_leaves_a_closed_ticket_closed(): void
    {
        $agent = $this->agentIn($this->it);
        $ticket = $this->ticketIn($this->it, $this->requester(), ['ticket_status_id' => $this->closedStatus->id, 'closed_at' => now()]);

        $this->actingAs($agent);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->set('replyForm.body', 'Closing note for the record.')
            ->call('reply');

        $this->assertTrue($ticket->refresh()->status->is($this->closedStatus));
    }

    public function test_the_thread_shows_the_newest_replies_and_loads_older_ones_on_request(): void
    {
        $requester = $this->requester();
        $ticket = $this->ticketIn($this->it, $requester);

        $perPage = TicketView::MESSAGES_PER_PAGE;
        $total = $perPage + 3;

        foreach (range(1, $total) as $number) {
            TicketMessage::factory()->for($ticket)->create([
                'user_id' => $requester->id,
                'body' => sprintf('Reply number %03d', $number),
                'created_at' => now()->subMinutes(1000 - $number),
            ]);
        }

        TicketMessage::factory()->internal()->for($ticket)->create(['body' => 'Hidden note', 'created_at' => now()]);

        $this->actingAs($requester);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->assertSet('olderMessageCount', 3)
            ->assertSee(__('Show older (:count more)', ['count' => 3]))
            ->assertSeeInOrder([sprintf('Reply number %03d', 4), sprintf('Reply number %03d', $total)])
            ->assertDontSee('Reply number 003')
            ->assertDontSee('Hidden note')
            ->call('showOlderMessages')
            ->assertSet('olderMessageCount', 0)
            ->assertSeeInOrder(['Reply number 001', sprintf('Reply number %03d', $total)])
            ->assertDontSeeHtml('data-test="show-older-messages-button"');
    }

    public function test_a_reply_needs_a_message(): void
    {
        $requester = $this->requester();
        $ticket = $this->ticketIn($this->it, $requester);

        $this->actingAs($requester);

        Livewire::test(TicketView::class, ['ticket' => $ticket])
            ->call('reply')
            ->assertHasErrors(['replyForm.body' => 'required']);
    }
}
