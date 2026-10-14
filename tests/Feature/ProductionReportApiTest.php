<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Client;
use App\Models\Instance;
use App\Models\Product;
use App\Models\Stage;
use App\Models\Funnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_and_aggregate_production(): void
    {
        $instance = Instance::create(['name' => 'Relatórios']);
        $admin = User::factory()->create(['instance_id' => $instance->id, 'type' => 'admin']);
        $seller = User::factory()->create(['instance_id' => $instance->id, 'type' => 'seller', 'name' => 'Ana', 'lastname' => 'Silva']);
        $product = Product::create(['instance_id' => $instance->id, 'title' => 'Consignado', 'prefix' => 'consignado', 'color' => '#000000']);
        $field = \App\Models\CustomField::create(['instance_id' => $instance->id, 'label' => 'Comissão', 'section' => 'business', 'type' => 'currency', 'visible_when' => 'Todos os produtos']);
        $funnel = Funnel::create(['instance_id' => $instance->id, 'name' => 'Principal']);
        $stage = Stage::create(['funnel_id' => $funnel->id, 'name' => 'Entrada', 'position' => 1]);
        $category = Category::create(['instance_id' => $instance->id, 'name' => 'Geral']);
        $client = Client::create(['instance_id' => $instance->id, 'fullname' => 'Cliente', 'type' => 'individual', 'registration' => '12345678901']);
        Business::create(['instance_id' => $instance->id, 'client_id' => $client->id, 'user_id' => $seller->id, 'category_id' => $category->id, 'product_id' => $product->id, 'funnel_id' => $funnel->id, 'stage_id' => $stage->id, 'status' => 3, 'value' => 1500, 'custom_data' => ['custom_fields' => [(string) $field->id => 250]], 'created_at' => '2026-10-05 10:00:00']);

        Sanctum::actingAs($admin);
        $this->getJson('/api/reports/production?from=2026-10-01&to=2026-10-31&product_id='.$product->id.'&sum_fields='.$field->id)
            ->assertOk()
            ->assertJsonPath('summary.total_production', 1)
            ->assertJsonPath('summary.total_value', 1500)
            ->assertJsonPath('summary.won_count', 1)
            ->assertJsonPath('custom_field_totals.'.$field->id, 250)
            ->assertJsonPath('rows.0.user_name', 'Ana Silva')
            ->assertJsonCount(0, 'loss_reasons')
            ->assertJsonPath('outcomes_by_product.0.won_count', 1);

        $savedId = $this->postJson('/api/reports/saved', [
            'name' => 'Produção de outubro',
            'filters' => [
                'from' => '2026-10-01', 'to' => '2026-10-31', 'userId' => '', 'productId' => (string) $product->id,
                'customFilters' => [], 'sumFieldIds' => [(string) $field->id],
            ],
        ])->assertCreated()->json('data.id');
        $this->getJson('/api/reports/saved')->assertOk()->assertJsonPath('data.0.name', 'Produção de outubro');
        $this->deleteJson('/api/reports/saved/'.$savedId)->assertNoContent();
    }

    public function test_seller_cannot_access_production_reports(): void
    {
        $instance = Instance::create(['name' => 'Relatórios']);
        $seller = User::factory()->create(['instance_id' => $instance->id, 'type' => 'seller']);
        Sanctum::actingAs($seller);

        $this->getJson('/api/reports/production?from=2026-10-01&to=2026-10-31')->assertForbidden();
    }
}
