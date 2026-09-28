<?php

namespace Tests\Feature\Users;

use App\Actions\Users\SyncUserDepartments;
use App\Enums\Permission;
use App\Livewire\Users\UserForm;
use App\Livewire\Users\UserList;
use App\Models\Department;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    public function test_user_managers_can_view_the_users_pages(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        $agent = $this->userWithRole('Agent');

        $this->get(route('users.index'))->assertOk()->assertSee($agent->email);
        $this->get(route('users.create'))->assertOk()->assertSee('New user - '.config('app.name'));
        $this->get(route('users.edit', $agent))->assertOk()->assertSee('Edit user - '.config('app.name'));
    }

    public function test_users_without_the_users_permission_cannot_access_the_users_pages(): void
    {
        $agent = $this->userWithRole('Agent');

        $this->actingAs($agent);

        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('users.create'))->assertForbidden();
        $this->get(route('users.edit', $agent))->assertForbidden();
    }

    public function test_the_users_menu_is_only_shown_to_user_managers(): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'))
            ->assertSee(route('users.index'));

        $this->actingAs($this->userWithRole('Agent'))
            ->get(route('dashboard'))
            ->assertDontSee(route('users.index'));
    }

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        $jane = User::factory()->create(['name' => 'Jane Agent'])->assignRole('Agent');
        $mark = User::factory()->deactivated()->create(['name' => 'Mark Manager'])->assignRole('Manager');

        Livewire::test(UserList::class)
            ->set('search', 'Jane')
            ->assertSee($jane->email)
            ->assertDontSee($mark->email)
            ->set('search', '')
            ->set('role', 'Manager')
            ->assertSee($mark->email)
            ->assertDontSee($jane->email)
            ->set('role', '')
            ->set('status', 'active')
            ->assertSee($jane->email)
            ->assertDontSee($mark->email);
    }

    public function test_a_user_can_be_created_with_one_role_and_is_sent_a_set_password_link(): void
    {
        Notification::fake();

        $this->actingAs($this->userWithRole('Admin'));

        $department = Department::factory()->create();

        Livewire::test(UserForm::class)
            ->set('userForm.name', 'New Agent')
            ->set('userForm.email', 'agent@example.com')
            ->set('userForm.role', 'Agent')
            ->set('userForm.departments', [(string) $department->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'agent@example.com')->firstOrFail();

        $this->assertSame(['Agent'], $user->getRoleNames()->all());
        $this->assertTrue($user->isActive());
        $this->assertNotNull($user->email_verified_at);

        Notification::assertSentTo($user, SetPasswordNotification::class);
    }

    public function test_a_user_can_be_created_in_several_departments(): void
    {
        Notification::fake();

        $this->actingAs($this->userWithRole('Admin'));

        $hr = Department::factory()->create(['name' => 'HR']);
        $it = Department::factory()->create(['name' => 'IT']);

        Livewire::test(UserForm::class)
            ->set('userForm.name', 'New Agent')
            ->set('userForm.email', 'agent@example.com')
            ->set('userForm.role', 'Agent')
            ->set('userForm.departments', [(string) $hr->id, (string) $it->id])
            ->assertSet('userForm.defaultDepartment', (string) $hr->id)
            ->set('userForm.defaultDepartment', (string) $it->id)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'agent@example.com')->firstOrFail();

        $this->assertEqualsCanonicalizing([$hr->id, $it->id], $user->departments()->pluck('departments.id')->all());
        $this->assertSame($it->id, $user->default_department_id);
    }

    public function test_editing_a_user_replaces_their_departments(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        $hr = Department::factory()->create(['name' => 'HR']);
        $it = Department::factory()->create(['name' => 'IT']);
        $facilities = Department::factory()->create(['name' => 'Workplace']);

        $user = User::factory()->inDepartment($hr)->create()->assignRole('Agent');
        $user->departments()->attach($it);

        Livewire::test(UserForm::class, ['user' => $user])
            ->assertSet('userForm.departments', [(string) $hr->id, (string) $it->id])
            ->assertSet('userForm.defaultDepartment', (string) $hr->id)
            ->set('userForm.departments', [(string) $it->id, (string) $facilities->id])
            ->assertSet('userForm.defaultDepartment', (string) $it->id)
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertEqualsCanonicalizing([$it->id, $facilities->id], $user->departments()->pluck('departments.id')->all());
        $this->assertSame($it->id, $user->default_department_id);
    }

    public function test_a_user_must_have_a_department(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        Livewire::test(UserForm::class)
            ->set('userForm.name', 'New Agent')
            ->set('userForm.email', 'agent@example.com')
            ->set('userForm.role', 'Agent')
            ->call('save')
            ->assertHasErrors(['userForm.departments' => 'required', 'userForm.defaultDepartment' => 'required']);

        $this->assertDatabaseMissing('users', ['email' => 'agent@example.com']);
    }

    public function test_the_default_department_must_be_one_of_the_users_departments(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        [$hr, $it] = Department::factory()->count(2)->create();

        Livewire::test(UserForm::class)
            ->set('userForm.name', 'New Agent')
            ->set('userForm.email', 'agent@example.com')
            ->set('userForm.role', 'Agent')
            ->set('userForm.departments', [(string) $hr->id])
            ->set('userForm.defaultDepartment', (string) $it->id)
            ->call('save')
            ->assertHasErrors(['userForm.defaultDepartment' => 'in']);

        $this->assertDatabaseMissing('users', ['email' => 'agent@example.com']);
    }

    public function test_syncing_departments_rejects_a_default_outside_them(): void
    {
        [$hr, $it] = Department::factory()->count(2)->create();

        $user = User::factory()->inDepartment($hr)->create();

        try {
            app(SyncUserDepartments::class)($user, [$hr->id], $it->id);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('defaultDepartment', $exception->errors());
        }

        $this->assertSame($hr->id, $user->refresh()->default_department_id);
    }

    public function test_inactive_departments_are_only_offered_to_their_current_members(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        $inactive = Department::factory()->inactive()->create();

        Livewire::test(UserForm::class)
            ->assertDontSee($inactive->name);

        $member = $this->userWithRole('Agent');
        $member->departments()->attach($inactive);

        Livewire::test(UserForm::class, ['user' => $member])
            ->assertSee($inactive->name);
    }

    public function test_the_list_can_be_filtered_by_department(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        $it = Department::factory()->create();

        $jane = $this->userWithRole('Agent');
        $jane->departments()->attach($it);
        $mark = $this->userWithRole('Agent');

        Livewire::test(UserList::class)
            ->set('department', (string) $it->id)
            ->assertSee($jane->email)
            ->assertDontSee($mark->email);
    }

    public function test_a_role_is_required_when_creating_a_user(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        Livewire::test(UserForm::class)
            ->set('userForm.name', 'New Agent')
            ->set('userForm.email', 'agent@example.com')
            ->call('save')
            ->assertHasErrors(['userForm.role' => 'required']);
    }

    public function test_emails_must_be_unique(): void
    {
        $this->actingAs($admin = $this->userWithRole('Admin'));

        Livewire::test(UserForm::class)
            ->set('userForm.name', 'Duplicate')
            ->set('userForm.email', $admin->email)
            ->set('userForm.role', 'Agent')
            ->call('save')
            ->assertHasErrors(['userForm.email' => 'unique']);
    }

    public function test_changing_the_role_replaces_the_previous_one(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        $user = $this->userWithRole('Agent');

        Livewire::test(UserForm::class, ['user' => $user])
            ->assertSet('userForm.role', 'Agent')
            ->set('userForm.name', 'Renamed')
            ->set('userForm.role', 'Manager')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('Renamed', $user->name);
        $this->assertSame(['Manager'], $user->getRoleNames()->all());
    }

    public function test_users_can_only_assign_roles_within_their_own_permissions(): void
    {
        $userManager = Role::create(['name' => 'User Manager']);
        $userManager->givePermissionTo(Permission::Users->value);

        $this->actingAs($this->userWithRole('User Manager'));

        Livewire::test(UserForm::class)
            ->set('userForm.name', 'Sneaky')
            ->set('userForm.email', 'sneaky@example.com')
            ->set('userForm.role', 'Admin')
            ->call('save')
            ->assertHasErrors(['userForm.role' => 'in']);

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_users_cannot_edit_users_with_more_permissions_than_themselves(): void
    {
        $userManager = Role::create(['name' => 'User Manager']);
        $userManager->givePermissionTo(Permission::Users->value);

        $this->actingAs($this->userWithRole('User Manager'));

        $this->get(route('users.edit', $this->userWithRole('Admin')))->assertForbidden();
    }

    public function test_users_cannot_change_their_own_role(): void
    {
        $this->actingAs($admin = $this->userWithRole('Admin'));

        Livewire::test(UserForm::class, ['user' => $admin])
            ->set('userForm.role', 'Agent')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['Admin'], $admin->refresh()->getRoleNames()->all());
    }

    public function test_another_role_manager_can_be_demoted_while_one_remains(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        $otherAdmin = $this->userWithRole('Admin');

        Livewire::test(UserForm::class, ['user' => $otherAdmin])
            ->set('userForm.role', 'Agent')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['Agent'], $otherAdmin->refresh()->getRoleNames()->all());
    }

    public function test_a_user_can_be_deactivated_and_reactivated(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        $agent = $this->userWithRole('Agent');

        Livewire::test(UserForm::class, ['user' => $agent])
            ->call('deactivate')
            ->assertHasNoErrors();

        $this->assertFalse($agent->refresh()->isActive());

        Livewire::test(UserForm::class, ['user' => $agent])
            ->call('reactivate')
            ->assertHasNoErrors();

        $this->assertTrue($agent->refresh()->isActive());
    }

    public function test_users_cannot_deactivate_themselves(): void
    {
        $this->actingAs($admin = $this->userWithRole('Admin'));

        Livewire::test(UserForm::class, ['user' => $admin])
            ->call('deactivate')
            ->assertForbidden();

        $this->assertTrue($admin->refresh()->isActive());
    }

    public function test_the_last_active_role_manager_cannot_be_deactivated(): void
    {
        $userManager = Role::create(['name' => 'Role Manager']);
        $userManager->givePermissionTo(array_column(Permission::cases(), 'value'));

        $admin = $this->userWithRole('Admin');
        $this->actingAs($roleManager = $this->userWithRole('Role Manager'));

        $roleManager->forceFill(['deactivated_at' => now()])->saveQuietly();

        Livewire::test(UserForm::class, ['user' => $admin])
            ->call('deactivate')
            ->assertHasErrors(['deactivate']);

        $this->assertTrue($admin->refresh()->isActive());
    }

    public function test_a_new_set_password_link_can_be_sent(): void
    {
        Notification::fake();

        $this->actingAs($this->userWithRole('Admin'));

        $agent = $this->userWithRole('Agent');

        Livewire::test(UserForm::class, ['user' => $agent])
            ->call('sendPasswordLink')
            ->assertHasNoErrors();

        Notification::assertSentTo($agent, SetPasswordNotification::class);
    }

    public function test_the_set_password_link_lets_the_user_choose_a_password(): void
    {
        Notification::fake();

        $this->actingAs($this->userWithRole('Admin'));

        Livewire::test(UserForm::class)
            ->set('userForm.name', 'New Agent')
            ->set('userForm.email', 'agent@example.com')
            ->set('userForm.role', 'Agent')
            ->set('userForm.departments', [(string) Department::factory()->create()->id])
            ->call('save');

        auth()->logout();

        $user = User::where('email', 'agent@example.com')->firstOrFail();

        Notification::assertSentTo($user, SetPasswordNotification::class, function (SetPasswordNotification $notification) use ($user) {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->post(route('login.store'), [
            'email' => 'agent@example.com',
            'password' => 'new-password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->inDepartment()->create()->assignRole($role);
    }
}
