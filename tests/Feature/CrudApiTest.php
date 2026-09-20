<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Disposition;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrudApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $instance = Instance::create(['name' => 'Instância de testes']);
        Sanctum::actingAs(User::factory()->create(['instance_id' => $instance->id]));
    }

    public function test_users_crud(): void
    {
        $created = $this->postJson('/api/users', [
            'name' => 'Maria',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()->json('data');

        $this->getJson('/api/users')->assertOk()->assertJsonPath('data.1.email', 'maria@example.com');
        $this->patchJson("/api/users/{$created['id']}", ['name' => 'Maria Silva'])->assertOk();
        $this->deleteJson("/api/users/{$created['id']}")->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $created['id']]);
    }

    public function test_deleted_user_requires_confirmation_before_restoration(): void
    {
        $instance = auth()->user()->instance;
        $user = User::factory()->create([
            'instance_id' => $instance->id,
            'email' => 'restaurar@example.com',
            'type' => 'seller',
        ]);
        $user->delete();

        $payload = [
            'name' => 'Usuário Restaurado',
            'email' => 'restaurar@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
            'type' => 'admin',
            'instance_id' => $instance->id,
        ];

        $this->postJson('/api/users', $payload)
            ->assertConflict()
            ->assertJsonPath('error.code', 'USER_RESTORE_REQUIRED');
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $this->postJson('/api/users', [...$payload, 'restore' => true])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.type', 'admin');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null, 'type' => 'admin']);
    }

    public function test_deleted_user_from_another_instance_cannot_be_restored(): void
    {
        $otherInstance = Instance::create(['name' => 'Outra instância']);
        $otherUser = User::factory()->create([
            'instance_id' => $otherInstance->id,
            'email' => 'outro-tenant@example.com',
        ]);
        $otherUser->delete();

        $this->postJson('/api/users', [
            'name' => 'Tentativa indevida',
            'email' => 'outro-tenant@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
            'restore' => true,
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'EMAIL_ALREADY_IN_USE');

        $this->assertSoftDeleted('users', ['id' => $otherUser->id]);
    }

    public function test_clients_and_phones_crud_and_relationship(): void
    {
        $client = $this->postJson('/api/clients/resolve', [
            'fullname' => 'João da Silva',
            'type' => 'individual',
            'registration' => '123.456.789-00',
            'phones' => [['number' => '+55 11 99999-9999', 'whatsapp' => true]],
        ])->assertCreated()->assertJsonPath('data.phones.0.whatsapp', true)->json('data');

        $this->postJson('/api/clients/resolve', [
            'fullname' => 'João da Silva',
            'type' => 'individual',
            'registration' => '123.456.789-00',
            'phones' => [['number' => '+55 11 99999-9999', 'whatsapp' => false]],
        ])->assertOk()
            ->assertJsonPath('data.id', $client['id'])
            ->assertJsonPath('data.phones.0.whatsapp', false);

        $this->assertDatabaseHas('clients', [
            'instance_id' => auth()->user()->instance_id,
            'type' => 'individual',
            'registration' => '12345678900',
        ]);
        $this->assertDatabaseHas('phones', ['number' => '5511999999999']);

        $this->getJson('/api/clients')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $client['id']);
    }

    public function test_clients_resolve_is_scoped_to_instance(): void
    {
        $firstUser = auth()->user();
        $secondInstance = Instance::create(['name' => 'Outra instância']);
        $secondUser = User::factory()->create(['instance_id' => $secondInstance->id]);

        $firstClient = $this->postJson('/api/clients/resolve', [
            'fullname' => 'Cliente A',
            'type' => 'individual',
            'registration' => '123.456.789-00',
        ])->assertCreated()->json('data');

        $this->postJson('/api/clients/resolve', [
            'fullname' => 'Cliente A atualizado',
            'type' => 'individual',
            'registration' => '12345678900',
        ])->assertOk()
            ->assertJsonPath('data.id', $firstClient['id'])
            ->assertJsonPath('data.fullname', 'Cliente A atualizado');

        Sanctum::actingAs($secondUser);
        $secondClient = $this->postJson('/api/clients/resolve', [
            'fullname' => 'Cliente B',
            'type' => 'individual',
            'registration' => '123.456.789-00',
        ])->assertCreated()
            ->assertJsonPath('data.instance_id', $secondInstance->id)
            ->json('data');

        $this->assertNotSame($firstClient['id'], $secondClient['id']);
        $this->assertDatabaseHas('clients', ['instance_id' => $firstUser->instance_id, 'registration' => '12345678900']);
        $this->assertDatabaseHas('clients', ['instance_id' => $secondInstance->id, 'registration' => '12345678900']);

        Sanctum::actingAs($firstUser);
        $this->getJson("/api/clients/{$secondClient['id']}")->assertNotFound();

        $this->assertSame(2, Client::where('registration', '12345678900')->count());
    }

    public function test_clients_search_returns_existing_client_for_authenticated_instance(): void
    {
        $client = Client::create([
            'instance_id' => auth()->user()->instance_id,
            'fullname' => 'Cliente Busca',
            'type' => 'individual',
            'registration' => '12345678900',
            'city' => 'Sao Paulo',
        ]);
        $client->phones()->create(['number' => '5511999999999', 'whatsapp' => true]);

        $this->getJson('/api/clients/search?registration=123.456.789-00')
            ->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.instance_id', auth()->user()->instance_id)
            ->assertJsonPath('data.fullname', 'Cliente Busca')
            ->assertJsonPath('data.phones.0.number', '5511999999999')
            ->assertJsonPath('data.phones.0.whatsapp', true);
    }

    public function test_clients_search_does_not_return_client_from_another_instance(): void
    {
        $otherInstance = Instance::create(['name' => 'Outra instância']);
        Client::create([
            'instance_id' => $otherInstance->id,
            'fullname' => 'Cliente de outra instancia',
            'type' => 'individual',
            'registration' => '12345678900',
        ]);

        $this->getJson('/api/clients/search?registration=123.456.789-00')->assertNotFound();
    }

    public function test_clients_search_returns_not_found_without_creating_client(): void
    {
        $this->assertDatabaseCount('clients', 0);

        $this->getJson('/api/clients/search?registration=123.456.789-00')->assertNotFound();

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_clients_search_ignores_instance_id_for_user_with_instance(): void
    {
        $ownClient = Client::create([
            'instance_id' => auth()->user()->instance_id,
            'fullname' => 'Cliente da instancia correta',
            'type' => 'individual',
            'registration' => '12345678900',
        ]);
        $otherInstance = Instance::create(['name' => 'Outra instância']);
        Client::create([
            'instance_id' => $otherInstance->id,
            'fullname' => 'Cliente de outra instancia',
            'type' => 'individual',
            'registration' => '12345678900',
        ]);

        $this->getJson("/api/clients/search?registration=123.456.789-00&instance_id={$otherInstance->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ownClient->id)
            ->assertJsonPath('data.instance_id', auth()->user()->instance_id);
    }

    public function test_clients_search_requires_valid_instance_for_master_without_instance(): void
    {
        Sanctum::actingAs(User::factory()->create(['instance_id' => null, 'type' => 'master']));

        $this->getJson('/api/clients/search?registration=123.456.789-00')->assertUnprocessable();
        $this->getJson('/api/clients/search?registration=123.456.789-00&instance_id=999999')->assertUnprocessable();
    }

    public function test_clients_search_allows_master_to_select_instance(): void
    {
        $instance = Instance::create(['name' => 'Instancia selecionada']);
        $client = Client::create([
            'instance_id' => $instance->id,
            'fullname' => 'Cliente Master',
            'type' => 'individual',
            'registration' => '12345678900',
        ]);

        Sanctum::actingAs(User::factory()->create(['instance_id' => null, 'type' => 'master']));

        $this->getJson("/api/clients/search?registration=123.456.789-00&instance_id={$instance->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.instance_id', $instance->id);
    }

    public function test_products_crud(): void
    {
        $funnel = $this->postJson('/api/funnels', ['name' => 'Funil comercial'])
            ->assertCreated()
            ->json('data');
        $product = $this->postJson('/api/products', [
            'title' => 'Portabilidade',
            'prefix' => 'PORT',
            'color' => '#3366FF',
            'funnel_ids' => [$funnel['id']],
        ])->assertCreated()->json('data');

        $this->assertSame($funnel['id'], $product['funnels'][0]['id']);

        $this->getJson('/api/products')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson("/api/products/{$product['id']}", ['title' => 'Portabilidade INSS'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Portabilidade INSS');
        $this->deleteJson("/api/products/{$product['id']}")->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $product['id']]);
    }

    public function test_dispositions_crud(): void
    {
        $disposition = $this->postJson('/api/dispositions', [
            'name' => 'Venda concluída',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Venda concluída')
            ->json('data');

        $this->getJson('/api/dispositions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $disposition['id']);

        $this->getJson("/api/dispositions/{$disposition['id']}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Venda concluída');

        $this->patchJson("/api/dispositions/{$disposition['id']}", [
            'name' => 'Contrato assinado',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Contrato assinado');

        $this->deleteJson("/api/dispositions/{$disposition['id']}")
            ->assertNoContent();

        $this->assertSoftDeleted('dispositions', ['id' => $disposition['id']]);
    }

    public function test_dispositions_validate_name_and_tenant_access(): void
    {
        $this->postJson('/api/dispositions', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['name']]]]);

        $this->postJson('/api/dispositions', ['name' => 'Duplicada'])->assertCreated();
        $this->postJson('/api/dispositions', ['name' => 'Duplicada'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['name']]]]);

        $otherInstance = Instance::create(['name' => 'Outra instância']);
        $otherDisposition = Disposition::create([
            'instance_id' => $otherInstance->id,
            'name' => 'Restrita',
        ]);

        $this->getJson("/api/dispositions/{$otherDisposition->id}")->assertNotFound();
        $this->patchJson("/api/dispositions/{$otherDisposition->id}", ['name' => 'Invadida'])
            ->assertNotFound();
        $this->deleteJson("/api/dispositions/{$otherDisposition->id}")->assertNotFound();
    }

    public function test_crud_routes_require_authentication(): void
    {
        auth()->forgetGuards();

        $this->getJson('/api/clients')->assertUnauthorized();
    }
}
