<?php

namespace Tests\Feature\TicketSettings;

use App\Enums\Permission;
use App\Livewire\Departments\DepartmentForm;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_department_managers_can_view_the_department_pages(): void
    {
        $this->actingAs($this->departmentManager());

        $department = Department::factory()->create(['name' => 'IT']);

        $this->get(route('departments.index'))->assertOk()->assertSee('IT');
        $this->get(route('departments.create'))->assertOk()->assertSee('New department - '.config('app.name'));
        $this->get(route('departments.edit', $department))->assertOk()->assertSee('Edit department - '.config('app.name'));
    }

    public function test_users_without_the_departments_permission_cannot_access_the_department_pages(): void
    {
        $this->actingAs($this->userWith(Permission::TicketStatuses, Permission::TicketPriorities));

        $department = Department::factory()->create();

        $this->get(route('departments.index'))->assertForbidden();
        $this->get(route('departments.create'))->assertForbidden();
        $this->get(route('departments.edit', $department))->assertForbidden();
    }

    public function test_the_ticket_settings_menu_only_shows_the_pages_the_user_can_manage(): void
    {
        $this->actingAs($this->departmentManager())
            ->get(route('dashboard'))
            ->assertSee(route('departments.index'))
            ->assertDontSee(route('ticket-statuses.index'))
            ->assertDontSee(route('ticket-priorities.index'));

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertDontSee(route('departments.index'));
    }

    public function test_a_department_can_be_created(): void
    {
        $this->actingAs($this->departmentManager());

        Livewire::test(DepartmentForm::class)
            ->set('name', 'Facilities')
            ->set('description', 'Buildings and equipment')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('departments.index'));

        $department = Department::where('name', 'Facilities')->firstOrFail();

        $this->assertTrue($department->is_active);
        $this->assertSame('Buildings and equipment', $department->description);
    }

    public function test_updating_a_department_keeps_its_members(): void
    {
        $this->actingAs($this->departmentManager());

        $member = User::factory()->create();

        $department = Department::factory()->create();
        $department->users()->attach($member);

        Livewire::test(DepartmentForm::class, ['department' => $department])
            ->set('name', 'Renamed')
            ->set('isActive', false)
            ->call('save')
            ->assertHasNoErrors();

        $department->refresh();

        $this->assertSame('Renamed', $department->name);
        $this->assertFalse($department->is_active);
        $this->assertSame([$member->id], $department->users()->pluck('users.id')->all());
    }

    public function test_department_names_must_be_unique(): void
    {
        $this->actingAs($this->departmentManager());

        Department::factory()->create(['name' => 'IT']);

        Livewire::test(DepartmentForm::class)
            ->set('name', 'IT')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);
    }

    public function test_a_department_can_be_deleted(): void
    {
        $this->actingAs($this->departmentManager());

        $department = Department::factory()->create();
        $department->users()->attach(User::factory()->create());

        Livewire::test(DepartmentForm::class, ['department' => $department])
            ->call('delete')
            ->assertRedirect(route('departments.index'));

        $this->assertModelMissing($department);
        $this->assertDatabaseCount('department_user', 0);
    }

    private function departmentManager(): User
    {
        return $this->userWith(Permission::Departments);
    }

    private function userWith(Permission ...$permissions): User
    {
        $role = Role::create(['name' => fake()->unique()->words(3, true)]);
        $role->givePermissionTo(array_column($permissions, 'value'));

        return User::factory()->create()->assignRole($role);
    }
}
