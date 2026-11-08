<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['name' => 'Alpha']);
        Sanctum::actingAs($this->admin);
    }

    public function test_index_lists_users_with_roles(): void
    {
        User::factory()->hr()->create(['name' => 'Bravo']);

        $this->getJson('/api/users')->assertOk()
            ->assertJsonPath('data.users.0.role', 'admin')
            ->assertJsonPath('data.users.1.name', 'Bravo')
            ->assertJsonPath('data.users.1.role', 'hr');
    }

    public function test_store_creates_user_with_hashed_password(): void
    {
        $this->postJson('/api/users', [
            'name' => 'New', 'email' => 'new@example.com', 'password' => 'secret123', 'role' => 'viewer',
        ])->assertCreated()
            ->assertJsonPath('message', 'User created.')
            ->assertJsonPath('data.user.role', 'viewer');

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'new@example.com', 'password' => 'secret123'])->assertOk();
    }

    public function test_store_rejects_unknown_role_and_duplicate_email(): void
    {
        $this->postJson('/api/users', [
            'name' => 'X', 'email' => $this->admin->email, 'password' => 'secret123', 'role' => 'boss',
        ])->assertStatus(422)->assertJsonValidationErrors(['role', 'email'], 'data.errors');
    }

    public function test_update_changes_role_and_resets_password(): void
    {
        $user = User::factory()->viewer()->create();

        $this->putJson("/api/users/{$user->id}", ['role' => 'hr', 'password' => 'newsecret1'])
            ->assertOk()->assertJsonPath('message', 'User updated.')->assertJsonPath('data.user.role', 'hr');

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'newsecret1'])->assertOk();
    }

    public function test_cannot_demote_last_admin_including_self(): void
    {
        $this->putJson("/api/users/{$this->admin->id}", ['role' => 'hr'])
            ->assertStatus(422)
            ->assertJsonPath('data.errors.role.0', 'At least one admin is required.');
        $this->assertSame(Role::Admin, $this->admin->fresh()->role);

        User::factory()->create();
        $this->putJson("/api/users/{$this->admin->id}", ['role' => 'hr'])->assertOk();
    }

    public function test_update_rejects_email(): void
    {
        $user = User::factory()->viewer()->create(['email' => 'keep@example.com']);

        $this->putJson("/api/users/{$user->id}", ['email' => 'x@y.z'])->assertStatus(422);
        $this->assertSame('keep@example.com', $user->fresh()->email);
    }

    public function test_hr_cannot_manage_users(): void
    {
        $hr = User::factory()->hr()->create();
        Sanctum::actingAs($hr);

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', [])->assertForbidden();
        $this->putJson("/api/users/{$hr->id}", ['role' => 'admin'])->assertForbidden();
    }
}
