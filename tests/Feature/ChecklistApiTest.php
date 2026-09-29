<?php

namespace Tests\Feature;

use App\Models\{Business, Category, ChecklistItemCompletion, Client, Funnel, Instance, Product, Stage, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChecklistApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_list_a_checklist_with_conditions(): void
    {
        [$instance, $funnel, $stage, $product] = $this->context();
        Sanctum::actingAs(User::factory()->create(['instance_id' => $instance->id, 'type' => 'admin']));

        $checklist = $this->postJson('/api/checklists', [
            'title' => 'Checklist de averbação',
            'description' => 'Documentos necessários',
            'funnel_ids' => [$funnel->id],
            'items' => [
                ['label' => 'Documento de identidade', 'position' => 1, 'required' => true],
            ],
            'conditions' => [[
                'funnel_id' => $funnel->id,
                'product_ids' => [$product->id],
                'min_stage_id' => $stage->id,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Checklist de averbação')
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.items.0.required', true)
            ->assertJsonPath('data.conditions.0.product_ids.0', $product->id)
            ->json('data');

        $this->assertDatabaseHas('checklists', ['id' => $checklist['id'], 'instance_id' => $instance->id]);
        $this->assertDatabaseHas('checklist_items', ['checklist_id' => $checklist['id'], 'position' => 1]);
        $this->assertDatabaseHas('checklist_conditions', ['checklist_id' => $checklist['id'], 'funnel_id' => $funnel->id]);

        $this->getJson('/api/checklists')
            ->assertOk()
            ->assertJsonPath('data.0.id', $checklist['id']);
    }

    public function test_checklist_is_available_for_a_business_and_completion_is_shared_and_audited(): void
    {
        [$instance, $funnel, $stage, $product, $business] = $this->context(true);
        $admin = User::factory()->create(['instance_id' => $instance->id, 'type' => 'admin']);
        Sanctum::actingAs($admin);

        $checklist = $this->postJson('/api/checklists', [
            'title' => 'Checklist do negócio',
            'funnel_ids' => [$funnel->id],
            'items' => [
                ['label' => 'Confirmar dados', 'position' => 1, 'required' => true],
            ],
        ])->assertCreated()->json('data');
        $itemId = $checklist['items'][0]['id'];

        $this->getJson("/api/businesses/{$business->id}/checklists")
            ->assertOk()
            ->assertJsonPath('data.0.items.0.done', false)
            ->assertJsonPath('data.0.items.0.completed_by', null);

        $this->patchJson("/api/businesses/{$business->id}/checklists/{$checklist['id']}/items/{$itemId}/completion", [
            'done' => true,
        ])->assertOk()
            ->assertJsonPath('data.done', true)
            ->assertJsonPath('data.completed_by', $admin->id);

        $this->assertDatabaseHas('checklist_item_completions', [
            'checklist_item_id' => $itemId,
            'business_id' => $business->id,
            'done' => true,
        ]);
        $this->assertDatabaseHas('histories', [
            'object_type' => 'checklist_item_completion',
            'object_id' => ChecklistItemCompletion::query()->firstOrFail()->id,
            'action' => 'checklist_item_completed',
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseCount('histories', 1);

        $this->patchJson("/api/businesses/{$business->id}/checklists/{$checklist['id']}/items/{$itemId}/completion", [
            'done' => true,
        ])->assertOk();
        $this->assertDatabaseCount('histories', 1);

        $this->getJson("/api/businesses/{$business->id}/checklists")
            ->assertJsonPath('data.0.items.0.done', true)
            ->assertJsonPath('data.0.items.0.completed_by', $admin->id);
    }

    public function test_seller_cannot_manage_checklists_but_can_mark_items_for_their_business(): void
    {
        [$instance, $funnel, $stage, $product, $business, $seller] = $this->context(true);
        Sanctum::actingAs($seller);

        $this->postJson('/api/checklists', [
            'title' => 'Não permitido',
            'funnel_ids' => [$funnel->id],
        ])->assertForbidden();

        $checklist = \App\Models\Checklist::create([
            'instance_id' => $instance->id,
            'title' => 'Checklist existente',
        ]);
        $checklist->funnels()->attach($funnel);
        $item = $checklist->items()->create(['label' => 'Item', 'position' => 1, 'required' => false]);

        $this->patchJson("/api/businesses/{$business->id}/checklists/{$checklist->id}/items/{$item->id}/completion", [
            'done' => true,
        ])->assertOk();

        $this->assertDatabaseHas('histories', [
            'object_type' => 'checklist_item_completion',
            'action' => 'checklist_item_completed',
            'user_id' => $seller->id,
        ]);
    }

    public function test_business_creation_and_stage_change_seed_pending_checklist_completions(): void
    {
        [$instance, $funnel, $stage, $product, $business, $seller] = $this->context(true);
        $admin = User::factory()->create(['instance_id' => $instance->id, 'type' => 'admin']);
        Sanctum::actingAs($admin);

        $allStagesChecklist = $this->postJson('/api/checklists', [
            'title' => 'Checklist do produto',
            'funnel_ids' => [$funnel->id],
            'items' => [['label' => 'Validar produto', 'position' => 1, 'required' => true]],
            'conditions' => [[
                'funnel_id' => $funnel->id,
                'product_ids' => [$product->id],
            ]],
        ])->assertCreated()->json('data');

        $newClient = Client::create([
            'instance_id' => $instance->id,
            'fullname' => 'Segundo cliente',
            'type' => 'individual',
            'registration' => fake()->unique()->numerify('###########'),
        ]);
        $newBusinessResponse = $this->postJson('/api/businesses', [
            'client_id' => $newClient->id,
            'user_id' => $seller->id,
            'category_id' => $business->category_id,
            'product_id' => $product->id,
            'funnel_id' => $funnel->id,
            'stage_id' => $stage->id,
        ]);
        if ($newBusinessResponse->status() !== 201) {
            $newBusinessResponse->dump();
        }
        $newBusiness = $newBusinessResponse->assertCreated()->json('data');
        $this->assertCount(1, $newBusiness['checklists']);
        $this->assertSame($allStagesChecklist['id'], $newBusiness['checklists'][0]['id']);
        $this->assertFalse($newBusiness['checklists'][0]['items'][0]['done']);

        $this->getJson('/api/businesses?stage_id='.$stage->id)
            ->assertOk()
            ->assertJsonPath('data.0.checklists.0.id', $allStagesChecklist['id'])
            ->assertJsonPath('data.0.checklists.0.items.0.done', false);

        $this->getJson('/api/businesses/'.$newBusiness['id'])
            ->assertOk()
            ->assertJsonPath('data.checklists.0.id', $allStagesChecklist['id'])
            ->assertJsonPath('data.checklists.0.items.0.done', false);

        $allStagesItemId = $allStagesChecklist['items'][0]['id'];
        $this->assertDatabaseHas('checklist_item_completions', [
            'checklist_item_id' => $allStagesItemId,
            'business_id' => $newBusiness['id'],
            'done' => false,
        ]);

        $laterStage = Stage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Análise',
            'position' => 2,
        ]);
        $secondProduct = Product::create([
            'instance_id' => $instance->id,
            'title' => 'Refinanciamento',
            'prefix' => fake()->unique()->lexify('PROD??'),
            'color' => '#654321',
        ]);
        $secondProduct->funnels()->attach($funnel);
        Category::findOrFail($business->category_id)->products()->attach($secondProduct);
        $laterChecklist = $this->postJson('/api/checklists', [
            'title' => 'Checklist da análise',
            'funnel_ids' => [$funnel->id],
            'items' => [['label' => 'Validar análise', 'position' => 1, 'required' => true]],
            'conditions' => [[
                'funnel_id' => $funnel->id,
                'product_ids' => [$secondProduct->id],
                'min_stage_id' => $laterStage->id,
            ]],
        ])->assertCreated()->json('data');

        $this->patchJson("/api/businesses/{$business->id}", [
            'product_id' => $secondProduct->id,
            'stage_id' => $laterStage->id,
        ])
            ->assertOk();

        $this->assertDatabaseHas('checklist_item_completions', [
            'checklist_item_id' => $laterChecklist['items'][0]['id'],
            'business_id' => $business->id,
            'done' => false,
        ]);
        $this->assertDatabaseMissing('histories', [
            'object_type' => 'checklist_item_completion',
        ]);
    }

    private function context(bool $withBusiness = false): array
    {
        $instance = Instance::create(['name' => fake()->unique()->company()]);
        $funnel = Funnel::create(['instance_id' => $instance->id, 'name' => 'Funil']);
        $stage = Stage::create(['funnel_id' => $funnel->id, 'name' => 'Documentação', 'position' => 1]);
        $product = Product::create([
            'instance_id' => $instance->id,
            'title' => 'Portabilidade',
            'prefix' => fake()->unique()->lexify('PROD??'),
            'color' => '#123456',
        ]);
        $product->funnels()->attach($funnel);

        if (! $withBusiness) {
            return [$instance, $funnel, $stage, $product];
        }

        $seller = User::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'seller',
            'funnel_id' => $funnel->id,
        ]);
        $client = Client::create([
            'instance_id' => $instance->id,
            'fullname' => 'Cliente',
            'type' => 'individual',
            'registration' => fake()->unique()->numerify('###########'),
        ]);
        $category = Category::create(['instance_id' => $instance->id, 'name' => 'Categoria']);
        $category->funnels()->attach($funnel);
        $category->products()->attach($product);
        $business = Business::create([
            'instance_id' => $instance->id,
            'client_id' => $client->id,
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'product_id' => $product->id,
            'funnel_id' => $funnel->id,
            'stage_id' => $stage->id,
        ]);

        return [$instance, $funnel, $stage, $product, $business, $seller];
    }
}
