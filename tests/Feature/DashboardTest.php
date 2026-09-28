<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tickets\InteractsWithTickets;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use InteractsWithTickets, RefreshDatabase;

    private Department $it;

    private Department $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTickets();

        $this->it = Department::factory()->create(['name' => 'IT']);
        $this->hr = Department::factory()->create(['name' => 'HR']);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_users_without_ticket_permissions_see_only_their_own_tickets(): void
    {
        $requester = $this->requester($this->it);
        $this->ticketIn($this->it, $requester, ['subject' => 'My printer']);
        $this->ticketIn($this->it, $requester, ['subject' => 'My old laptop', 'ticket_status_id' => $this->closedStatus->id, 'closed_at' => now()->subDays(2)]);
        $this->ticketIn($this->it, $this->requester(), ['subject' => 'Someone elses ticket']);

        $this->actingAs($requester);

        $this->get(route('dashboard'))->assertOk()->assertSee(__('The tickets you opened'));

        $dashboard = Livewire::test(Dashboard::class)
            ->assertSee(__('My open tickets'))
            ->assertDontSee(__('Waiting longest for someone'))
            ->assertSee('My printer')
            ->assertDontSee('Someone elses ticket');

        $this->assertSame('mine', $dashboard->instance()->scope);
        $this->assertSame(1, $dashboard->instance()->counts['open']);
        $this->assertSame(1, $dashboard->instance()->counts['closedThisWeek']);
        $this->assertSame(2, $dashboard->instance()->counts['total']);
    }

    public function test_agents_see_the_tickets_in_their_departments(): void
    {
        $agent = $this->agentIn($this->it);
        $this->ticketIn($this->it, $this->requester(), ['subject' => 'Assigned laptop', 'assignee_id' => $agent->id]);
        $this->ticketIn($this->it, $this->requester(), ['subject' => 'Newer unassigned', 'created_at' => now()->subDay()]);
        $this->ticketIn($this->it, $this->requester(), ['subject' => 'Older unassigned', 'created_at' => now()->subDays(5)]);
        $this->ticketIn($this->hr, $this->requester(), ['subject' => 'Payslip question']);

        $this->actingAs($agent);

        $dashboard = Livewire::test(Dashboard::class)
            ->assertSee(__('Tickets in your departments, and the ones you opened'))
            ->assertSee('Assigned laptop')
            ->assertSeeInOrder(['Older unassigned', 'Newer unassigned'])
            ->assertDontSee('Payslip question');

        $this->assertSame('departments', $dashboard->instance()->scope);
        $this->assertSame(
            ['open' => 3, 'unassigned' => 2, 'assignedToMe' => 1, 'closedThisWeek' => 0, 'total' => 3],
            $dashboard->instance()->counts,
        );
        $this->assertSame(['IT'], $dashboard->instance()->openByDepartment->pluck('name')->all());
    }

    public function test_holders_of_tickets_all_see_every_department(): void
    {
        $this->ticketIn($this->it, $this->requester());
        $this->ticketIn($this->hr, $this->requester());
        $this->ticketIn($this->hr, $this->requester());

        $this->actingAs($this->manager());

        $dashboard = Livewire::test(Dashboard::class)->assertSee(__('Every ticket, across all departments'));

        $this->assertSame('all', $dashboard->instance()->scope);
        $this->assertSame(3, $dashboard->instance()->counts['open']);
        $this->assertSame(['HR', 'IT'], $dashboard->instance()->openByDepartment->pluck('name')->all());
        $this->assertSame(
            [$this->openStatus->name => 3],
            $dashboard->instance()->openByStatus->pluck('tickets_count', 'name')->all(),
        );
    }

    public function test_closed_this_week_ignores_tickets_closed_earlier(): void
    {
        $this->ticketIn($this->it, $this->requester(), ['ticket_status_id' => $this->closedStatus->id, 'closed_at' => now()->subDays(3)]);
        $this->ticketIn($this->it, $this->requester(), ['ticket_status_id' => $this->closedStatus->id, 'closed_at' => now()->subDays(10)]);

        $this->actingAs($this->manager());

        $this->assertSame(1, Livewire::test(Dashboard::class)->instance()->counts['closedThisWeek']);
    }

    public function test_the_tiles_link_to_the_filtered_ticket_list(): void
    {
        $this->actingAs($this->agentIn($this->it));

        Livewire::test(Dashboard::class)
            ->assertSeeHtml('href="'.route('tickets.index', ['unassigned' => 1]).'"')
            ->assertSeeHtml('href="'.route('tickets.index', ['assignedToMe' => 1]).'"');
    }
}
