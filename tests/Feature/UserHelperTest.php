<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UserHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_signed_in_user(): void
    {
        $this->actingAs($signedIn = User::factory()->create());

        $this->assertTrue(user()->is($signedIn));
    }

    public function test_it_aborts_with_403_when_nobody_is_signed_in(): void
    {
        try {
            user();
            $this->fail('Expected the helper to abort.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
