<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_business_workflow(): void
    {
        $instance = Instance::create(['name' => 'Empresa A']);
        $user = User::factory()->create(['instance_id' => $instance->id]);
        Sanctum::actingAs($user);

        $funnel = $this->postJson('/api/funnels', [
            'name' => 'Comercial',
        ])->assertCreated()->assertJsonPath('data.instance_id', $instance->id)->json('data');
        $user->update(['funnel_id' => $funnel['id']]);
        $secondaryFunnel = $this->postJson('/api/funnels', [
            'name' => 'Renovação',
        ])->assertCreated()->json('data');

        $product = $this->postJson('/api/products', [
            'title' => 'Novo contrato',
            'prefix' => 'NOVO',
            'color' => '#112233',
            'funnel_ids' => [$funnel['id'], $secondaryFunnel['id']],
        ])->assertCreated()
            ->assertJsonPath('data.instance_id', $instance->id)
            ->assertJsonCount(2, 'data.funnels')
            ->json('data');

        $category = $this->postJson('/api/categories', [
            'name' => 'INSS',
            'funnel_ids' => [$funnel['id'], $secondaryFunnel['id']],
            'product_ids' => [$product['id']],
        ])->assertCreated()->assertJsonCount(2, 'data.funnels')->json('data');

        $stage = $this->postJson('/api/stages', [
            'funnel_id' => $funnel['id'],
            'name' => 'Proposta enviada',
            'position' => 1,
            'is_final' => true,
        ])->assertCreated()->json('data');
        $laterStage = $this->postJson('/api/stages', [
            'funnel_id' => $funnel['id'],
            'name' => 'Em negociação',
            'position' => 2,
        ])->assertCreated()->json('data');

        $client = Client::create([
            'fullname' => 'Cliente Teste',
            'type' => 'individual',
            'registration' => '11122233344',
        ]);

        $business = $this->postJson('/api/businesses', [
            'client_id' => $client->id,
            'user_id' => $user->id,
            'category_id' => $category['id'],
            'product_id' => $product['id'],
            'funnel_id' => $funnel['id'],
            'stage_id' => $laterStage['id'],
            'status' => 1,
            'value' => 125050,
            'notes' => 'Aguardando retorno do cliente.',
            'custom_data' => [
                'custom_fields' => [
                    '3' => 2,
                    '4' => 2,
                    'bank' => 'Banco do Brasil',
                    'term' => 84,
                    'contracts' => [
                        ['number' => '123', 'balance' => 1500.50],
                    ],
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.instance_id', $instance->id)
            ->assertJsonPath('data.stage.id', $stage['id'])
            ->assertJsonPath('data.customData.custom_fields.bank', 'Banco do Brasil')
            ->assertJsonPath('data.customData.custom_fields.3', 2)
            ->assertJsonPath('data.customData.custom_fields.4', 2)
            ->assertJsonPath('data.customData.custom_fields.term', 84)
            ->assertJsonPath('data.customData.custom_fields.contracts.0.number', '123')
            ->json('data');

        $this->assertDatabaseHas('businesses', [
            'id' => $business['id'],
            'custom_data' => json_encode([
                'custom_fields' => [
                    '3' => 2,
                    '4' => 2,
                    'bank' => 'Banco do Brasil',
                    'term' => 84,
                    'contracts' => [['number' => '123', 'balance' => 1500.50]],
                ],
            ]),
        ]);

        $this->assertDatabaseHas('histories', [
            'object_type' => 'business', 'object_id' => $business['id'], 'action' => 'Negócio criado',
        ]);

        $this->patchJson("/api/businesses/{$business['id']}", [
            'status' => 2,
            'value' => 150000,
        ])->assertOk()
            ->assertJsonPath('data.status', 2)
            ->assertJsonPath('data.value', 150000);

        $this->assertDatabaseHas('histories', [
            'object_type' => 'business', 'object_id' => $business['id'], 'action' => 'Negócio marcado como ganho',
        ]);

        $this->getJson('/api/businesses')->assertOk()->assertJsonCount(1, 'data');

        $master = User::factory()->create([
            'instance_id' => null,
            'type' => 'master',
        ]);

        $this->postJson('/api/businesses', [
            'client_id' => $client->id,
            'user_id' => $master->id,
            'category_id' => $category['id'],
            'product_id' => $product['id'],
            'funnel_id' => $funnel['id'],
            'stage_id' => $laterStage['id'],
            'status' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.user.id', $master->id);
    }

    public function test_business_rejects_product_category_and_funnel_stage_mismatches(): void
    {
        $instance = Instance::create(['name' => 'Empresa A']);
        $user = User::factory()->create(['instance_id' => $instance->id]);
        Sanctum::actingAs($user);

        $funnel = $this->postJson('/api/funnels', ['name' => 'Funil A'])->assertCreated()->json('data');
        $otherFunnel = $this->postJson('/api/funnels', ['name' => 'Funil B'])->assertCreated()->json('data');
        $product = $this->postJson('/api/products', [
            'title' => 'Portabilidade', 'prefix' => 'PORT', 'color' => '#123456', 'funnel_ids' => [$funnel['id']],
        ])->assertCreated()->json('data');
        $category = $this->postJson('/api/categories', [
            'name' => 'SIAPE', 'funnel_ids' => [$funnel['id']],
        ])->assertCreated()->json('data');
        $stage = $this->postJson('/api/stages', [
            'funnel_id' => $otherFunnel['id'], 'name' => 'Análise', 'position' => 1,
        ])->assertCreated()->json('data');
        $client = Client::create(['fullname' => 'Cliente', 'type' => 'individual', 'registration' => '99988877766']);

        $this->postJson('/api/businesses', [
            'client_id' => $client->id,
            'user_id' => $user->id,
            'category_id' => $category['id'],
            'product_id' => $product['id'],
            'funnel_id' => $funnel['id'],
            'stage_id' => $stage['id'],
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['relationships']]]]);
    }

    public function test_business_responsible_must_be_admin_or_belong_to_business_funnel(): void
    {
        $instance = Instance::create(['name' => 'Empresa A']);
        $admin = User::factory()->create(['instance_id' => $instance->id, 'type' => 'admin']);
        Sanctum::actingAs($admin);

        $funnel = $this->postJson('/api/funnels', ['name' => 'Funil A'])->assertCreated()->json('data');
        $otherFunnel = $this->postJson('/api/funnels', ['name' => 'Funil B'])->assertCreated()->json('data');
        $seller = User::factory()->create(['instance_id' => $instance->id, 'type' => 'seller', 'funnel_id' => $funnel['id']]);
        $otherSeller = User::factory()->create(['instance_id' => $instance->id, 'type' => 'seller', 'funnel_id' => $otherFunnel['id']]);
        $businessAdmin = User::factory()->create(['instance_id' => $instance->id, 'type' => 'admin']);
        $category = $this->postJson('/api/categories', ['name' => 'Categoria', 'funnel_ids' => [$funnel['id']]])->assertCreated()->json('data');
        $stage = $this->postJson('/api/stages', ['funnel_id' => $funnel['id'], 'name' => 'Entrada', 'position' => 1])->assertCreated()->json('data');
        $client = Client::create(['fullname' => 'Cliente', 'type' => 'individual', 'registration' => '99988877766']);

        $business = $this->postJson('/api/businesses', [
            'client_id' => $client->id,
            'user_id' => $seller->id,
            'category_id' => $category['id'],
            'funnel_id' => $funnel['id'],
            'stage_id' => $stage['id'],
        ])->assertCreated()->json('data');

        $this->patchJson("/api/businesses/{$business['id']}", ['user_id' => $businessAdmin->id])
            ->assertOk()
            ->assertJsonPath('data.user.id', $businessAdmin->id);

        $this->patchJson("/api/businesses/{$business['id']}", ['user_id' => $otherSeller->id])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['user_id']]]]);
    }

    public function test_client_custom_fields_are_saved_by_resolve_and_returned_with_business(): void
    {
        $instance = Instance::create(['name' => 'Empresa A']);
        $user = User::factory()->create(['instance_id' => $instance->id, 'type' => 'admin']);
        Sanctum::actingAs($user);

        $funnel = $this->postJson('/api/funnels', ['name' => 'Funil'])->assertCreated()->json('data');
        $category = $this->postJson('/api/categories', ['name' => 'Categoria', 'funnel_ids' => [$funnel['id']]])->assertCreated()->json('data');
        $stage = $this->postJson('/api/stages', ['funnel_id' => $funnel['id'], 'name' => 'Entrada', 'position' => 1])->assertCreated()->json('data');

        $client = $this->postJson('/api/clients/resolve', [
            'fullname' => 'Cliente com ficha',
            'type' => 'individual',
            'registration' => '12345678901',
            'extra' => [
                'custom_fields' => [
                    '10' => 'MAT-123',
                    '11' => 'Banco XPTO',
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.extra.custom_fields.10', 'MAT-123')
            ->assertJsonPath('data.customData.custom_fields.11', 'Banco XPTO')
            ->json('data');

        $business = $this->postJson('/api/businesses', [
            'client_id' => $client['id'],
            'user_id' => $user->id,
            'category_id' => $category['id'],
            'funnel_id' => $funnel['id'],
            'stage_id' => $stage['id'],
        ])->assertCreated()
            ->assertJsonPath('data.client.extra.custom_fields.10', 'MAT-123')
            ->assertJsonPath('data.client.customData.custom_fields.11', 'Banco XPTO')
            ->json('data');

        $this->getJson("/api/businesses/{$business['id']}")
            ->assertOk()
            ->assertJsonPath('data.client.custom_data.custom_fields.10', 'MAT-123');
    }

    public function test_business_can_be_created_without_a_product(): void
    {
        $instance = Instance::create(['name' => 'Empresa A']);
        $user = User::factory()->create(['instance_id' => $instance->id]);
        Sanctum::actingAs($user);

        $funnel = $this->postJson('/api/funnels', ['name' => 'Funil'])->assertCreated()->json('data');
        $user->update(['funnel_id' => $funnel['id']]);
        $category = $this->postJson('/api/categories', [
            'name' => 'Categoria', 'funnel_ids' => [$funnel['id']],
        ])->assertCreated()->json('data');
        $stage = $this->postJson('/api/stages', [
            'funnel_id' => $funnel['id'], 'name' => 'Entrada', 'position' => 1,
        ])->assertCreated()->json('data');
        $client = Client::create(['fullname' => 'Cliente sem produto', 'type' => 'individual', 'registration' => '12345678901']);

        $this->postJson('/api/businesses', [
            'client_id' => $client->id, 'user_id' => $user->id,
            'category_id' => $category['id'], 'product_id' => null,
            'funnel_id' => $funnel['id'], 'stage_id' => $stage['id'],
        ])->assertCreated()
            ->assertJsonPath('data.product_id', null)
            ->assertJsonPath('data.stage.id', $stage['id']);
    }

    public function test_tenant_cannot_access_another_tenants_product(): void
    {
        $firstInstance = Instance::create(['name' => 'Empresa A']);
        $secondInstance = Instance::create(['name' => 'Empresa B']);
        $firstUser = User::factory()->create(['instance_id' => $firstInstance->id]);
        $secondUser = User::factory()->create(['instance_id' => $secondInstance->id]);

        Sanctum::actingAs($firstUser);
        $funnel = $this->postJson('/api/funnels', ['name' => 'Funil'])->assertCreated()->json('data');
        $productId = $this->postJson('/api/products', [
            'title' => 'Produto privado', 'prefix' => 'PRIV', 'color' => '#ABCDEF', 'funnel_ids' => [$funnel['id']],
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($secondUser);
        $this->getJson("/api/products/{$productId}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_business_resource_preserves_numeric_custom_field_ids(): void
    {
        $business = new \App\Models\Business;
        $business->forceFill(['custom_data' => ['custom_fields' => [3 => 2, 4 => 2]]]);
        $resource = new \App\Http\Resources\BusinessResource($business);
        $payload = $resource->response()->getData(true);
        $this->assertSame(['3' => 2, '4' => 2], $payload['data']['customData']['custom_fields']);
        $this->assertStringContainsString('"custom_fields":{"3":2,"4":2}', $resource->response()->getContent());
    }
}
