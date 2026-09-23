<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('home'))->assertRedirect(route('dashboard'));

        $this->followingRedirects()
            ->get(route('home'))
            ->assertOk()
            ->assertSee(__('Log in to your account'));
    }

    public function test_authenticated_users_are_redirected_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('home'))->assertRedirect(route('dashboard'));
    }
}
