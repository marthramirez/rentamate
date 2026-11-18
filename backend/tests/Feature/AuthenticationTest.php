<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_boarding_routes_require_a_token(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_active_user_can_sign_in_and_out(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonMissingPath('data.user.password');

        $token = $login->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->postJson('/api/logout')->assertOk();

        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_inactive_user_and_bad_password_cannot_sign_in(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'password',
            'status' => User::STATUS_INACTIVE,
        ]);

        $this->postJson('/api/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'password',
            'role' => User::ROLE_STAFF,
        ]);

        $this->postJson('/api/login', [
            'email' => 'staff@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->issueToken();
        $user->tokens()->update(['expires_at' => now()->subMinute()]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/dashboard')
            ->assertUnauthorized();
    }

    public function test_admin_can_manage_users_and_staff_cannot(): void
    {
        $staff = User::factory()->staff()->create();
        $this->signIn($staff);

        $this->getJson('/api/dashboard')->assertOk();
        $this->getJson('/api/users')->assertForbidden();

        $this->signIn();

        $this->postJson('/api/users', [
            'name' => 'Front Desk',
            'email' => 'desk@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'staff',
            'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('data.role', 'staff')
            ->assertJsonMissingPath('data.password');

        $this->postJson('/api/users', [
            'name' => 'Front Desk',
            'email' => 'desk@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'staff',
            'status' => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_the_last_active_administrator_cannot_be_removed(): void
    {
        $admin = $this->signIn();

        $this->putJson('/api/users/'.$admin->id, [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'staff',
            'status' => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors('role');

        $this->deleteJson('/api/users/'.$admin->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $other = User::factory()->create();
        $this->signIn();

        $this->deleteJson('/api/users/'.$other->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('users', [
            'id' => $other->id,
            'status' => 'inactive',
        ]);
    }

    public function test_deactivating_a_user_revokes_tokens_and_password_change_keeps_the_current_one(): void
    {
        $staff = User::factory()->staff()->create(['password' => 'old-password']);
        $staffToken = $staff->issueToken();

        $this->signIn();
        $this->putJson('/api/users/'.$staff->id, [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => 'staff',
            'status' => 'inactive',
        ])->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->getJson('/api/me')
            ->assertUnauthorized();

        $user = User::factory()->create(['password' => 'old-password']);
        $current = $user->issueToken();
        $other = $user->issueToken();

        $this->withHeader('Authorization', 'Bearer '.$current)
            ->putJson('/api/account/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$other)
            ->getJson('/api/me')
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer '.$current)
            ->getJson('/api/me')
            ->assertOk();

        $this->putJson('/api/account/password', [
            'current_password' => 'old-password',
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');
    }
}
