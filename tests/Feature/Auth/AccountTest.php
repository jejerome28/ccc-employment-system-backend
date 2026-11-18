<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_renames_self_and_stays_signed_in(): void
    {
        $user = User::factory()->hr()->create(['name' => 'Old']);
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->putJson('/api/me', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('message', 'Account updated.')
            ->assertJsonPath('data.user.name', 'New Name');
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/me')->assertOk();
    }

    public function test_password_change_revokes_every_token_including_current(): void
    {
        $user = User::factory()->create();
        $otherTab = $user->createToken('api')->plainTextToken;
        $current = $user->createToken('api')->plainTextToken;

        $this->withToken($current)->putJson('/api/me', [
            'current_password' => 'password',
            'password' => 'brandnew99',
            'password_confirmation' => 'brandnew99',
        ])->assertOk()->assertJsonPath('message', 'Account updated.');
        $this->assertSame(0, $user->tokens()->count());
        $this->app['auth']->forgetGuards();

        $this->withToken($current)->getJson('/api/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($otherTab)->getJson('/api/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'brandnew99'])->assertOk();
    }

    public function test_wrong_or_missing_current_password_is_rejected_and_session_kept(): void
    {
        $user = User::factory()->hr()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->putJson('/api/me', [
            'current_password' => 'nope-nope', 'password' => 'brandnew99', 'password_confirmation' => 'brandnew99',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password'], 'data.errors');

        $this->withToken($token)->putJson('/api/me', [
            'password' => 'brandnew99', 'password_confirmation' => 'brandnew99',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password'], 'data.errors');
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/me')->assertOk();
    }

    public function test_blank_name_and_bad_password_input_are_rejected(): void
    {
        $user = User::factory()->hr()->create(['name' => 'Keep']);
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->putJson('/api/me', ['name' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['name'], 'data.errors');
        $this->withToken($token)->putJson('/api/me', [
            'current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors(['password'], 'data.errors');
        $this->withToken($token)->putJson('/api/me', [
            'current_password' => 'password', 'password' => 'brandnew99', 'password_confirmation' => 'different9',
        ])->assertStatus(422)->assertJsonValidationErrors(['password'], 'data.errors');
        $this->withToken($token)->putJson('/api/me', ['email' => 'x@y.z'])->assertStatus(422);

        $this->assertSame('Keep', $user->fresh()->name);
    }

    public function test_viewer_cannot_update_account(): void
    {
        $viewer = User::factory()->viewer()->create();
        $token = $viewer->createToken('api')->plainTextToken;

        $this->withToken($token)->putJson('/api/me', ['name' => 'Kiosk 2'])->assertForbidden();
    }

    public function test_account_updates_are_throttled(): void
    {
        $user = User::factory()->hr()->create();
        $token = $user->createToken('api')->plainTextToken;

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->withToken($token)->putJson('/api/me', [
                'current_password' => 'nope-nope', 'password' => 'brandnew99', 'password_confirmation' => 'brandnew99',
            ])->assertStatus(422);
        }

        $this->withToken($token)->putJson('/api/me', [
            'current_password' => 'nope-nope', 'password' => 'brandnew99', 'password_confirmation' => 'brandnew99',
        ])->assertStatus(429);
    }

    public function test_current_password_without_new_password_is_rejected(): void
    {
        $user = User::factory()->hr()->create(['name' => 'Keep']);
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->putJson('/api/me', ['name' => 'Changed', 'current_password' => 'password'])
            ->assertStatus(422)->assertJsonValidationErrors(['password'], 'data.errors');
        $this->withToken($token)->putJson('/api/me', ['current_password' => 'password', 'password' => '', 'password_confirmation' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['password'], 'data.errors');
        $this->app['auth']->forgetGuards();

        $this->assertSame('Keep', $user->fresh()->name);
        $this->withToken($token)->getJson('/api/me')->assertOk();
    }
}
