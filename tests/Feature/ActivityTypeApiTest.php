<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\Funnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityTypeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_uses_assigned_instance_even_when_another_is_sent(): void
    {
        $assigned = Instance::create(['name' => 'Assigned']);
        $other = Instance::create(['name' => 'Other']);

        foreach (['seller', 'admin', 'master'] as $type) {
            Sanctum::actingAs(User::factory()->create(['type' => $type, 'instance_id' => $assigned->id]));

            foreach ([[], ['instance_id' => $other->id]] as $index => $selection) {
                $name = $type.$index;
                $this->postJson('/api/activity-types', ['activity_type' => $name, ...$selection])
                    ->assertCreated()->assertJsonPath('data.instance_id', $assigned->id);
                $this->assertDatabaseHas('activity_types', ['activity_type' => $name, 'instance_id' => $assigned->id]);
            }
        }
    }

    public function test_master_without_instance_must_select_valid_instance(): void
    {
        $instance = Instance::create(['name' => 'Selected']);
        Sanctum::actingAs(User::factory()->create(['type' => 'master', 'instance_id' => null]));

        $this->postJson('/api/activity-types', ['activity_type' => 'Call'])->assertUnprocessable();
        $this->postJson('/api/activity-types', ['activity_type' => 'Call', 'instance_id' => 999999])->assertUnprocessable();
        $this->postJson('/api/activity-types', ['activity_type' => 'Call', 'instance_id' => $instance->id])
            ->assertCreated()->assertJsonPath('data.instance_id', $instance->id);
    }

    public function test_non_master_without_instance_cannot_select_one(): void
    {
        $instance = Instance::create(['name' => 'Restricted']);

        foreach (['seller', 'admin'] as $type) {
            Sanctum::actingAs(User::factory()->create(['type' => $type, 'instance_id' => null]));
            $this->postJson('/api/activity-types', ['activity_type' => 'Call', 'instance_id' => $instance->id])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('activity_types', 0);
    }

    public function test_types_can_target_multiple_funnels_or_all_funnels(): void
    {
        $instance = Instance::create(['name' => 'Selected']);
        Sanctum::actingAs(User::factory()->create(['type' => 'admin', 'instance_id' => $instance->id]));
        $first = Funnel::create(['name' => 'First', 'instance_id' => $instance->id]);
        $second = Funnel::create(['name' => 'Second', 'instance_id' => $instance->id]);
        $third = Funnel::create(['name' => 'Third', 'instance_id' => $instance->id]);

        $restricted = $this->postJson('/api/activity-types', [
            'activity_type' => 'Visita', 'funnel_ids' => [$first->id, $second->id],
        ])->assertCreated()->assertJsonPath('data.activity_type', 'Visita')
            ->assertJsonCount(2, 'data.funnel_ids')->json('data.id');
        foreach ([$first->id, $second->id] as $funnelId) {
            $this->assertDatabaseHas('activity_type_funnel', ['activity_type_id' => $restricted, 'funnel_id' => $funnelId]);
        }
        $global = $this->postJson('/api/activity-types', [
            'activity_type' => 'Ligação', 'funnel_ids' => [],
        ])->assertCreated()->assertJsonPath('data.funnel_ids', [])->json('data.id');
        $this->postJson('/api/activity-types', ['activity_type' => 'Retorno'])
            ->assertCreated()->assertJsonPath('data.funnel_ids', []);

        foreach ([$first->id, $second->id] as $funnelId) {
            $this->getJson('/api/activity-types?funnel_id='.$funnelId)->assertOk()
                ->assertJsonFragment(['id' => $restricted])->assertJsonFragment(['id' => $global]);
        }
        $ids = $this->getJson('/api/activity-types?funnel_id='.$third->id)->assertOk()->json('data');
        $this->assertNotContains($restricted, array_column($ids, 'id'));
        $this->assertContains($global, array_column($ids, 'id'));
    }

    public function test_funnel_selection_validates_array_and_instance(): void
    {
        $instance = Instance::create(['name' => 'Selected']);
        $other = Instance::create(['name' => 'Other']);
        Sanctum::actingAs(User::factory()->create(['type' => 'admin', 'instance_id' => $instance->id]));
        $own = Funnel::create(['name' => 'Own', 'instance_id' => $instance->id]);
        $foreign = Funnel::create(['name' => 'Foreign', 'instance_id' => $other->id]);
        foreach (['invalid', [$own->id, $own->id], [$foreign->id], [999999]] as $selection) {
            $this->postJson('/api/activity-types', [
                'activity_type' => 'Inválido', 'funnel_ids' => $selection,
            ])->assertUnprocessable();
        }
        $this->assertDatabaseCount('activity_types', 0);
        $this->assertDatabaseCount('activity_type_funnel', 0);
    }

    public function test_migration_preserves_existing_funnel_links(): void
    {
        $migration = require database_path('migrations/2026_09_07_140000_create_activity_type_funnel_table.php');
        $migration->down();
        $instance = Instance::create(['name' => 'Legacy']);
        $funnel = Funnel::create(['name' => 'Legacy funnel', 'instance_id' => $instance->id]);
        $id = \Illuminate\Support\Facades\DB::table('activity_types')->insertGetId([
            'activity_type' => 'Legacy type', 'instance_id' => $instance->id, 'funnel_id' => $funnel->id,
        ]);
        $migration->up();
        $this->assertDatabaseHas('activity_type_funnel', ['activity_type_id' => $id, 'funnel_id' => $funnel->id]);
        $migration->down();
        $this->assertDatabaseHas('activity_types', ['id' => $id, 'funnel_id' => $funnel->id]);
        $migration->up();
    }
}
