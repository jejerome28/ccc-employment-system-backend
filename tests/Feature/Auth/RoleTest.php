<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use DatabaseMigrations;

    public function test_migration_makes_existing_users_admin_and_new_default_viewer(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

        DB::table('users')->insert(['name' => 'Old', 'email' => 'old@example.com', 'password' => 'x']);

        $this->artisan('migrate')->assertSuccessful();

        $this->assertSame('admin', DB::table('users')->where('email', 'old@example.com')->value('role'));

        DB::table('users')->insert(['name' => 'New', 'email' => 'new@example.com', 'password' => 'x']);

        $this->assertSame('viewer', DB::table('users')->where('email', 'new@example.com')->value('role'));
    }

    public function test_me_includes_role(): void
    {
        Sanctum::actingAs(User::factory()->hr()->create());

        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.user.role', 'hr');
    }

    public function test_seeder_creates_one_account_per_role_with_admin_password(): void
    {
        $keys = ['ADMIN_PASSWORD'];
        $old = array_map(fn ($k) => [getenv($k), $_ENV[$k] ?? null, $_SERVER[$k] ?? null], array_combine($keys, $keys));

        try {
            foreach (['ADMIN_PASSWORD' => 'secret-admin'] as $key => $value) {
                putenv("$key=$value");
                $_ENV[$key] = $_SERVER[$key] = $value;
            }

            $this->seed(DatabaseSeeder::class);

            foreach (['admin@example.com' => 'admin', 'hr@example.com' => 'hr', 'viewer@example.com' => 'viewer'] as $email => $role) {
                $user = User::where('email', $email)->first();
                $this->assertSame($role, $user->role->value);
                $this->assertTrue(Hash::check('secret-admin', $user->password));
            }
        } finally {
            foreach ($old as $k => [$env, $e, $sv]) {
                $env === false ? putenv($k) : putenv("$k=$env");
                $e === null ? Arr::forget($_ENV, $k) : $_ENV[$k] = $e;
                $sv === null ? Arr::forget($_SERVER, $k) : $_SERVER[$k] = $sv;
            }
        }
    }
}
