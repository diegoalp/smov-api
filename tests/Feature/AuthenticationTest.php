<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_a_valid_api_key(): void
    {
        $this->defaultHeaders = [];

        $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'error' => [
                    'status' => 401,
                    'code' => 'INVALID_API_KEY',
                    'message' => 'A chave de acesso à API não foi informada ou é inválida.',
                ],
            ]);

        $this->withHeader('X-API-Key', 'invalid-api-key')
            ->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123',
            ])->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_API_KEY');
    }

    public function test_user_can_register_and_receive_a_token(): void
    {
        Carbon::setTestNow('2026-08-30 12:00:00');

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_name' => 'tests',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token', 'token_type', 'expires_in', 'expires_at'])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 3600)
            ->assertJsonPath('expires_at', '2026-08-30T13:00:00+00:00');

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $response->json('user.id'),
            'expires_at' => '2026-08-30 13:00:00',
        ]);
    }

    public function test_user_can_login_access_profile_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonStructure(['expires_in', 'expires_at'])
            ->assertJsonPath('expires_in', 3600);

        $token = $login->json('token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.status', 422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['email']]]]);
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
}
