<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_return_a_working_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logged in.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user', ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
            ->assertJsonStructure(['data' => ['token', 'expires_at']]);

        $this->withToken($response->json('data.token'))->getJson('/api/me')->assertOk();
    }

    public function test_wrong_password_and_unknown_email_get_identical_response(): void
    {
        $user = User::factory()->create();
        $expected = ['success' => false, 'message' => 'Invalid credentials.', 'data' => null];

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnauthorized()->assertExactJson($expected);
        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertUnauthorized()->assertExactJson($expected);
    }

    public function test_missing_fields_return_validation_envelope(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonValidationErrors(['email', 'password'], 'data.errors');
    }

    public function test_malformed_email_and_array_password_are_rejected(): void
    {
        $this->postJson('/api/login', ['email' => 'not-an-email', 'password' => ['x']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password'], 'data.errors');
    }

    public function test_account_locks_after_five_failures_even_with_correct_password(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized();
        }

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertTooManyRequests()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['retry_after']]);
    }

    public function test_email_casing_does_not_bypass_lockout(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', ['email' => Str::upper($user->email), 'password' => 'wrong']);
        }

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();
    }

    public function test_lock_expires_after_fifteen_minutes(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->travel(901)->seconds();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_successful_login_resets_failure_count(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 4) as $attempt) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong']);
        }
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        foreach (range(1, 4) as $attempt) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_route_throttle_caps_ten_requests_per_minute_per_ip(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->postJson('/api/login', ['email' => "user{$attempt}@example.com", 'password' => 'wrong'])
                ->assertUnauthorized();
        }

        $this->postJson('/api/login', ['email' => 'user11@example.com', 'password' => 'wrong'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['retry_after']]);
    }
}
