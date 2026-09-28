<?php

namespace Tests\Feature\TicketSettings;

use App\Enums\Permission;
use App\Livewire\TicketPriorities\TicketPriorityForm;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketPriorityManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_priority_managers_can_view_the_priority_pages(): void
    {
        $this->actingAs($this->priorityManager());

        $priority = TicketPriority::factory()->create(['name' => 'Critical']);

        $this->get(route('ticket-priorities.index'))->assertOk()->assertSee('Critical');
        $this->get(route('ticket-priorities.create'))->assertOk()->assertSee('New priority - '.config('app.name'));
        $this->get(route('ticket-priorities.edit', $priority))->assertOk()->assertSee('Edit priority - '.config('app.name'));
    }

    public function test_users_without_the_ticket_priorities_permission_cannot_access_the_priority_pages(): void
    {
        $this->actingAs($this->userWith(Permission::Departments, Permission::TicketStatuses));

        $this->get(route('ticket-priorities.index'))->assertForbidden();
        $this->get(route('ticket-priorities.create'))->assertForbidden();
        $this->get(route('ticket-priorities.edit', TicketPriority::factory()->create()))->assertForbidden();
    }

    public function test_a_priority_can_be_created(): void
    {
        $this->actingAs($this->priorityManager());

        Livewire::test(TicketPriorityForm::class)
            ->set('ticketPriorityForm.name', 'Critical')
            ->set('ticketPriorityForm.color', 'red')
            ->set('ticketPriorityForm.sortOrder', 50)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('ticket-priorities.index'));

        $priority = TicketPriority::where('name', 'Critical')->firstOrFail();

        $this->assertSame('red', $priority->color->value);
        $this->assertSame(50, $priority->sort_order);
        $this->assertTrue($priority->is_default);
    }

    public function test_making_a_priority_the_default_replaces_the_previous_default(): void
    {
        $this->actingAs($this->priorityManager());

        $normal = TicketPriority::factory()->default()->create();
        $high = TicketPriority::factory()->create();

        Livewire::test(TicketPriorityForm::class, ['ticketPriority' => $high])
            ->set('ticketPriorityForm.isDefault', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($high->refresh()->is_default);
        $this->assertFalse($normal->refresh()->is_default);
    }

    public function test_the_default_priority_cannot_be_unset_directly(): void
    {
        $this->actingAs($this->priorityManager());

        $normal = TicketPriority::factory()->default()->create();

        Livewire::test(TicketPriorityForm::class, ['ticketPriority' => $normal])
            ->set('ticketPriorityForm.isDefault', false)
            ->call('save')
            ->assertHasErrors(['ticketPriorityForm.isDefault']);
    }

    public function test_a_priority_can_be_deleted_unless_it_is_the_default(): void
    {
        $this->actingAs($this->priorityManager());

        $normal = TicketPriority::factory()->default()->create();
        $low = TicketPriority::factory()->create();

        Livewire::test(TicketPriorityForm::class, ['ticketPriority' => $low])
            ->call('delete')
            ->assertRedirect(route('ticket-priorities.index'));

        $this->assertModelMissing($low);

        Livewire::test(TicketPriorityForm::class, ['ticketPriority' => $normal])
            ->call('delete')
            ->assertForbidden();

        $this->assertModelExists($normal);
    }

    public function test_a_priority_used_by_tickets_cannot_be_deleted(): void
    {
        $this->actingAs($this->priorityManager());

        $high = TicketPriority::factory()->create();
        Ticket::factory()->create(['ticket_priority_id' => $high->id]);

        $this->get(route('ticket-priorities.edit', $high))->assertSee(__('Tickets use this priority, so it cannot be deleted.'));

        Livewire::test(TicketPriorityForm::class, ['ticketPriority' => $high])
            ->call('delete')
            ->assertForbidden();

        $this->assertModelExists($high);
    }

    private function priorityManager(): User
    {
        return $this->userWith(Permission::TicketPriorities);
    }

    private function userWith(Permission ...$permissions): User
    {
        $role = Role::create(['name' => fake()->unique()->words(3, true)]);
        $role->givePermissionTo(array_column($permissions, 'value'));

        return User::factory()->create()->assignRole($role);
    }
}
