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

    public function test_logout_keeps_other_device_tokens(): void
    {
        $user = User::factory()->create();
        $laptopToken = $user->createToken('api')->plainTextToken;
        $phoneToken = $user->createToken('api')->plainTextToken;

        $this->withToken($laptopToken)->postJson('/api/logout')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->withToken($phoneToken)->getJson('/api/me')->assertOk();
    }

    public function test_logout_without_token_is_unauthenticated(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}
