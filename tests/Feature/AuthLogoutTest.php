<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthLogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_post_logout_revokes_the_personal_access_token(): void
    {
        $user = $this->adminUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Logged out successfully']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * The deprecated GET alias must keep revoking the token while the old
     * frontend build is still deployed (rolling-deploy safety).
     */
    public function test_deprecated_get_logout_alias_revokes_the_personal_access_token(): void
    {
        $user = $this->adminUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Logged out successfully'])
            ->assertHeader('Deprecation', 'true');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_revoked_token_can_no_longer_authenticate(): void
    {
        $user = $this->adminUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/logout')->assertOk();

        // The test harness reuses the same application instance between
        // requests, so the resolved user is still cached on the guard.
        // Production runs every request in a fresh application instance.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_api_requests_return_401_json_instead_of_500(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    public function test_login_returns_the_token_and_the_user(): void
    {
        $user = $this->adminUser();

        $response = $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'token', 'user'])
            ->assertJsonPath('user.email', $user->email);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_update_profile_updates_name_and_email(): void
    {
        Sanctum::actingAs($user = $this->adminUser());

        $response = $this->putJson('/api/user/profile', [
            'name'  => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'New Name')
            ->assertJsonPath('user.email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'name'  => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    public function test_update_profile_rejects_an_email_owned_by_someone_else(): void
    {
        Sanctum::actingAs($this->adminUser());

        $other = User::factory()->create(['email' => 'taken@example.com']);

        $this->putJson('/api/user/profile', [
            'email' => 'taken@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertDatabaseHas('users', ['id' => $other->id, 'email' => 'taken@example.com']);
    }
}
