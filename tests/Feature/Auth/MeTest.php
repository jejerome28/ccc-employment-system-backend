<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'OK',
                'data' => ['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]],
            ]);
    }

    public function test_rejects_request_without_token(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.', 'data' => null]);
    }

    public function test_rejects_request_without_accept_header_as_json_not_redirect(): void
    {
        $this->get('/api/me')
            ->assertUnauthorized()
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.', 'data' => null]);
    }

    public function test_rejects_expired_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->travel(481)->minutes();

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }
}
