<?php

namespace Tests\Feature\TicketSettings;

use App\Enums\Permission;
use App\Livewire\TicketStatuses\TicketStatusForm;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketStatusManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_status_managers_can_view_the_status_pages(): void
    {
        $this->actingAs($this->statusManager());

        $status = TicketStatus::factory()->create(['name' => 'Waiting']);

        $this->get(route('ticket-statuses.index'))->assertOk()->assertSee('Waiting');
        $this->get(route('ticket-statuses.create'))->assertOk()->assertSee('New status - '.config('app.name'));
        $this->get(route('ticket-statuses.edit', $status))->assertOk()->assertSee('Edit status - '.config('app.name'));
    }

    public function test_users_without_the_ticket_statuses_permission_cannot_access_the_status_pages(): void
    {
        $this->actingAs($this->userWith(Permission::Departments, Permission::TicketPriorities));

        $this->get(route('ticket-statuses.index'))->assertForbidden();
        $this->get(route('ticket-statuses.create'))->assertForbidden();
        $this->get(route('ticket-statuses.edit', TicketStatus::factory()->create()))->assertForbidden();
    }

    public function test_the_first_status_becomes_the_default(): void
    {
        $this->actingAs($this->statusManager());

        Livewire::test(TicketStatusForm::class)
            ->set('ticketStatusForm.name', 'Open')
            ->set('ticketStatusForm.color', 'blue')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('ticket-statuses.index'));

        $this->assertTrue(TicketStatus::where('name', 'Open')->firstOrFail()->is_default);
    }

    public function test_making_a_status_the_default_replaces_the_previous_default(): void
    {
        $this->actingAs($this->statusManager());

        $open = TicketStatus::factory()->default()->create();
        $new = TicketStatus::factory()->create();

        Livewire::test(TicketStatusForm::class, ['ticketStatus' => $new])
            ->set('ticketStatusForm.isDefault', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($new->refresh()->is_default);
        $this->assertFalse($open->refresh()->is_default);
    }

    public function test_the_default_status_cannot_be_unset_directly(): void
    {
        $this->actingAs($this->statusManager());

        $open = TicketStatus::factory()->default()->create();

        Livewire::test(TicketStatusForm::class, ['ticketStatus' => $open])
            ->set('ticketStatusForm.isDefault', false)
            ->call('save')
            ->assertHasErrors(['ticketStatusForm.isDefault']);

        $this->assertTrue($open->refresh()->is_default);
    }

    public function test_the_default_status_cannot_count_as_closed(): void
    {
        $this->actingAs($this->statusManager());

        Livewire::test(TicketStatusForm::class)
            ->set('ticketStatusForm.name', 'Done')
            ->set('ticketStatusForm.isDefault', true)
            ->set('ticketStatusForm.isClosed', true)
            ->call('save')
            ->assertHasErrors(['ticketStatusForm.isClosed']);
    }

    public function test_the_color_must_be_a_supported_badge_color(): void
    {
        $this->actingAs($this->statusManager());

        Livewire::test(TicketStatusForm::class)
            ->set('ticketStatusForm.name', 'Odd')
            ->set('ticketStatusForm.color', 'not-a-color')
            ->call('save')
            ->assertHasErrors(['ticketStatusForm.color']);
    }

    public function test_a_status_can_be_deleted_unless_it_is_the_default(): void
    {
        $this->actingAs($this->statusManager());

        $open = TicketStatus::factory()->default()->create();
        $pending = TicketStatus::factory()->create();

        Livewire::test(TicketStatusForm::class, ['ticketStatus' => $pending])
            ->call('delete')
            ->assertRedirect(route('ticket-statuses.index'));

        $this->assertModelMissing($pending);

        Livewire::test(TicketStatusForm::class, ['ticketStatus' => $open])
            ->call('delete')
            ->assertForbidden();

        $this->assertModelExists($open);
    }

    public function test_a_status_used_by_tickets_cannot_be_deleted(): void
    {
        $this->actingAs($this->statusManager());

        $pending = TicketStatus::factory()->create();
        Ticket::factory()->create(['ticket_status_id' => $pending->id]);

        $this->get(route('ticket-statuses.edit', $pending))->assertSee(__('Tickets use this status, so it cannot be deleted.'));

        Livewire::test(TicketStatusForm::class, ['ticketStatus' => $pending])
            ->call('delete')
            ->assertForbidden();

        $this->assertModelExists($pending);
    }

    private function statusManager(): User
    {
        return $this->userWith(Permission::TicketStatuses);
    }

    private function userWith(Permission ...$permissions): User
    {
        $role = Role::create(['name' => fake()->unique()->words(3, true)]);
        $role->givePermissionTo(array_column($permissions, 'value'));

        return User::factory()->create()->assignRole($role);
    }
}
