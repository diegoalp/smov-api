<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PDOException;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateTenantUser(): void
    {
        $instance = Instance::create(['name' => 'Instância de testes']);
        Sanctum::actingAs(User::factory()->create(['instance_id' => $instance->id]));
    }

    public function test_unauthenticated_error_uses_the_standard_contract(): void
    {
        $this->getJson('/api/clients')
            ->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'error' => [
                    'status' => 401,
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Você precisa estar autenticado para acessar este recurso.',
                ],
            ]);
    }

    public function test_validation_error_includes_friendly_field_errors(): void
    {
        $this->authenticateTenantUser();

        $this->postJson('/api/products', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.status', 422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => ['message', 'details' => ['fields' => ['title', 'prefix', 'color']]],
            ]);
    }

    public function test_missing_resource_and_endpoint_are_distinguished(): void
    {
        $this->authenticateTenantUser();

        $this->getJson('/api/products/999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->getJson('/api/endpoint-inexistente')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'ENDPOINT_NOT_FOUND');
    }

    public function test_invalid_http_method_returns_405_with_allowed_methods(): void
    {
        $this->authenticateTenantUser();

        $this->postJson('/api/products/1')
            ->assertStatus(405)
            ->assertJsonPath('error.status', 405)
            ->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED');
    }

    public function test_database_connection_error_returns_a_friendly_service_unavailable_response(): void
    {
        Route::get('/api/database-unavailable', function (): never {
            throw new QueryException(
                'mysql',
                'select 1',
                [],
                new PDOException('Connection refused', 2002),
            );
        });

        $this->getJson('/api/database-unavailable')
            ->assertServiceUnavailable()
            ->assertHeader('Retry-After', '5')
            ->assertExactJson([
                'success' => false,
                'error' => [
                    'status' => 503,
                    'code' => 'DATABASE_UNAVAILABLE',
                    'message' => 'Não foi possível acessar os dados no momento. Aguarde alguns instantes e tente novamente.',
                ],
            ]);
    }
}
