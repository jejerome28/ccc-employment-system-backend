<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Logged out.', 'data' => null]);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $user->createToken('api');
        $phoneToken = $user->createToken('api')->plainTextToken;

        $this->withToken($phoneToken)->postJson('/api/logout')->assertOk();

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_logout_without_token_is_unauthenticated(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}
