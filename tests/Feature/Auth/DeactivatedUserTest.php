<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeactivatedUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_users_cannot_log_in(): void
    {
        $user = User::factory()->deactivated()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => 'This account has been deactivated.']);

        $this->assertGuest();
    }

    public function test_deactivated_users_with_a_wrong_password_get_the_usual_error(): void
    {
        $user = User::factory()->deactivated()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_signed_in_users_are_logged_out_once_deactivated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->forceFill(['deactivated_at' => now()])->save();

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'This account has been deactivated.');

        $this->assertGuest();
    }
}
