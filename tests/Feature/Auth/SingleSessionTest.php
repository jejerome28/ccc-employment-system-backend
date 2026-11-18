<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_older_token_is_rejected_and_deleted(): void
    {
        $user = User::factory()->hr()->create();
        $older = $user->createToken('api')->plainTextToken;
        $newer = $user->createToken('api')->plainTextToken;

        $this->withToken($older)->getJson('/api/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Signed in on another device.');
        $this->assertSame(1, $user->tokens()->count());
        $this->app['auth']->forgetGuards();

        $this->withToken($newer)->getJson('/api/me')->assertOk();
    }

    public function test_newest_token_passes(): void
    {
        $user = User::factory()->hr()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->putJson('/api/me', ['name' => 'Still Here'])->assertOk();
    }

    public function test_stale_token_cannot_reach_any_route(): void
    {
        $viewer = User::factory()->viewer()->create();
        $kioskA = $viewer->createToken('api')->plainTextToken;
        $viewer->createToken('api');

        $this->withToken($kioskA)->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_other_users_tokens_do_not_interfere(): void
    {
        $a = User::factory()->hr()->create();
        $tokenA = $a->createToken('api')->plainTextToken;
        User::factory()->hr()->create()->createToken('api');

        $this->withToken($tokenA)->getJson('/api/me')->assertOk();
    }
}
