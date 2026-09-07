<?php
namespace Tests\Feature;
use App\Models\{Activity, ActivityType, Business, Category, Client, Funnel, Instance, Stage, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class ActivityApiTest extends TestCase {
    use RefreshDatabase;
    private function business(User $owner): Business {
        $funnel = Funnel::create(['instance_id' => $owner->instance_id, 'name' => 'Funil '.Business::count()]);
        $stage = Stage::create(['funnel_id' => $funnel->id, 'name' => 'Entrada', 'position' => 1]);
        $category = Category::create(['instance_id' => $owner->instance_id, 'name' => 'Categoria '.Business::count()]);
        $category->funnels()->attach($funnel);
        $client = Client::create(['fullname' => 'Cliente', 'type' => 'individual', 'registration' => fake()->unique()->numerify('###########')]);
        return Business::create(['instance_id' => $owner->instance_id, 'client_id' => $client->id, 'user_id' => $owner->id,
            'category_id' => $category->id, 'funnel_id' => $funnel->id, 'stage_id' => $stage->id, 'status' => 1, 'value' => 0]);
    }
    private function activity(User $owner, string $when): Activity {
        $business = $this->business($owner);
        $type = ActivityType::firstOrCreate(['instance_id' => $owner->instance_id, 'activity_type' => 'Ligação']);
        return Activity::create(['instance_id' => $owner->instance_id, 'business_id' => $business->id, 'user_id' => $owner->id,
            'activity_type_id' => $type->id, 'title' => 'Retorno', 'scheduled_at' => $when, 'status' => 'pending']);
    }
    public function test_creation_and_completion_with_correct_timezone_and_owner(): void {
        $instance = Instance::create(['name' => 'A']);
        $owner = User::factory()->create(['instance_id' => $instance->id, 'type' => 'seller']);
        Sanctum::actingAs($owner);
        $business = $this->business($owner);
        $type = ActivityType::create(['instance_id' => $instance->id, 'activity_type' => 'Ligação']);
        $body = ['business_id' => $business->id, 'activity_type_id' => $type->id, 'title' => 'Ligar ao cliente',
            'description' => 'Confirmar proposta', 'scheduled_at' => '2026-09-07T10:00:00-03:00'];
        $id = $this->postJson('/api/activities', $body)->assertCreated()->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.user_id', $owner->id)->assertJsonPath('data.scheduled_at', '2026-09-07T13:00:00.000000Z')
            ->assertJsonPath('data.activity_type.activity_type', 'Ligação')->json('data.id');
        $this->postJson('/api/activities', $body + ['status' => 'completed'])->assertUnprocessable();
        $this->postJson('/api/activities', $body + ['user_id' => 999])->assertUnprocessable();
        $this->patchJson('/api/activities/'.$id, ['status' => 'completed'])->assertOk()->assertJsonPath('data.status', 'completed');
        $completedAt = Activity::find($id)->completed_at;
        $this->travel(1)->hours();
        $this->patchJson('/api/activities/'.$id, ['status' => 'completed'])->assertOk();
        $this->assertTrue(Activity::find($id)->completed_at->equalTo($completedAt));
        $this->patchJson('/api/activities/'.$id, ['status' => 'pending'])->assertUnprocessable();
        $this->getJson('/api/activities?business_id='.$business->id)->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson('/api/activities/'.$id)->assertNoContent();
    }
    public function test_role_visibility_and_completion_are_tenant_scoped(): void {
        $a = Instance::create(['name' => 'A']); $b = Instance::create(['name' => 'B']);
        $seller = User::factory()->create(['instance_id' => $a->id, 'type' => 'seller']);
        $subordinate = User::factory()->create(['instance_id' => $a->id, 'type' => 'seller', 'supervisor_id' => $seller->id]);
        $other = User::factory()->create(['instance_id' => $a->id, 'type' => 'seller']);
        $foreign = User::factory()->create(['instance_id' => $b->id, 'type' => 'seller', 'supervisor_id' => $seller->id]);
        $own = $this->activity($seller, now()); $team = $this->activity($subordinate, now());
        $hidden = $this->activity($other, now()); $outside = $this->activity($foreign, now());
        Sanctum::actingAs($seller);
        $this->getJson('/api/activities')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/activities/today?timezone=UTC')->assertOk()->assertJsonCount(2, 'data');
        $this->patchJson('/api/activities/'.$team->id, ['status' => 'completed'])->assertOk();
        foreach ([$hidden, $outside] as $item) {
            $this->getJson('/api/activities/'.$item->id)->assertNotFound();
            $this->patchJson('/api/activities/'.$item->id, ['status' => 'completed'])->assertNotFound();
            $this->deleteJson('/api/activities/'.$item->id)->assertNotFound();
        }
        foreach (['admin', 'master'] as $role) {
            Sanctum::actingAs(User::factory()->create(['instance_id' => $a->id, 'type' => $role]));
            $this->getJson('/api/activities')->assertOk()->assertJsonCount(3, 'data');
        }
        Sanctum::actingAs(User::factory()->create(['instance_id' => null, 'type' => 'master']));
        $this->getJson('/api/activities?instance_id='.$b->id)->assertOk()->assertJsonCount(1, 'data');
    }
    public function test_today_and_month_use_exclusive_end_and_local_day(): void {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-08T01:00:00Z'));
        $instance = Instance::create(['name' => 'A']);
        $owner = User::factory()->create(['instance_id' => $instance->id]);
        Sanctum::actingAs($owner);
        $this->activity($owner, '2026-09-07 02:59:59');
        $start = $this->activity($owner, '2026-09-07 03:00:00');
        $end = $this->activity($owner, '2026-09-08 02:59:59');
        $this->activity($owner, '2026-09-08 03:00:00');
        $this->getJson('/api/activities/today?timezone=America/Sao_Paulo')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $start->id)->assertJsonPath('data.1.id', $end->id);
        $this->getJson('/api/activities?start=2026-09-07T03:00:00Z&end=2026-09-08T03:00:00Z')
            ->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/activities/today?timezone=invalid')->assertUnprocessable();
    }
    public function test_creation_rejects_other_owners_tenants_and_wrong_funnel_types(): void {
        $a = Instance::create(['name' => 'A']); $b = Instance::create(['name' => 'B']);
        $owner = User::factory()->create(['instance_id' => $a->id, 'type' => 'seller']);
        $other = User::factory()->create(['instance_id' => $a->id, 'type' => 'seller']);
        $foreign = User::factory()->create(['instance_id' => $b->id, 'type' => 'seller']);
        $own = $this->activity($owner, now()); $hidden = $this->activity($other, now()); $outside = $this->activity($foreign, now());
        Sanctum::actingAs($owner);
        $base = ['activity_type_id' => $own->activity_type_id, 'title' => 'Teste', 'scheduled_at' => now()->toISOString()];
        $this->postJson('/api/activities', $base + ['business_id' => $hidden->business_id])->assertForbidden();
        $this->postJson('/api/activities', $base + ['business_id' => $outside->business_id])->assertNotFound();
        $own->activityType->funnels()->attach($hidden->business->funnel_id);
        $this->postJson('/api/activities', $base + ['business_id' => $own->business_id])->assertUnprocessable();
    }

    public function test_assignment_rules_for_seller_supervisor_admin_and_master(): void {
        $instance = Instance::create(['name' => 'Assignment']);
        $supervisor = User::factory()->create(['instance_id' => $instance->id, 'type' => 'seller']);
        $seller = User::factory()->create(['instance_id' => $instance->id, 'type' => 'seller', 'supervisor_id' => $supervisor->id]);
        $peer = User::factory()->create(['instance_id' => $instance->id, 'type' => 'seller', 'supervisor_id' => $supervisor->id]);
        $admin = User::factory()->create(['instance_id' => $instance->id, 'type' => 'admin']);
        $master = User::factory()->create(['instance_id' => null, 'type' => 'master']);
        $business = $this->business($seller);
        $type = ActivityType::create(['instance_id' => $instance->id, 'activity_type' => 'Retorno']);
        $base = ['instance_id' => $instance->id, 'business_id' => $business->id, 'activity_type_id' => $type->id,
            'title' => 'Agendamento', 'scheduled_at' => now()->toISOString()];
        foreach ([
            [$seller, [$seller->id, $supervisor->id]],
            [$supervisor, [$supervisor->id, $seller->id]],
            [$admin, [$admin->id, $seller->id, $supervisor->id]],
            [$master, [$master->id, $seller->id, $supervisor->id]],
        ] as [$actor, $allowed]) {
            Sanctum::actingAs($actor);
            $ids = $this->getJson('/api/activities/assignees?business_id='.$business->id.'&instance_id='.$instance->id)
                ->assertOk()->json('data.*.id');
            $this->assertEqualsCanonicalizing($allowed, $ids);
            foreach ($allowed as $id) {
                $this->postJson('/api/activities', $base + ['user_id' => $id])
                    ->assertCreated()->assertJsonPath('data.user_id', $id)->assertJsonPath('data.status', 'pending');
            }
            $this->postJson('/api/activities', $base + ['user_id' => $peer->id])->assertUnprocessable();
        }
        Sanctum::actingAs($peer);
        $this->getJson('/api/activities/assignees?business_id='.$business->id)->assertForbidden();
    }

    public function test_assignment_ignores_foreign_or_deleted_supervisors(): void {
        $instance = Instance::create(['name' => 'A']);
        $other = Instance::create(['name' => 'B']);
        $foreign = User::factory()->create(['instance_id' => $other->id, 'type' => 'seller']);
        $seller = User::factory()->create(['instance_id' => $instance->id, 'type' => 'seller', 'supervisor_id' => $foreign->id]);
        $business = $this->business($seller);
        Sanctum::actingAs($seller);
        $this->getJson('/api/activities/assignees?business_id='.$business->id)->assertOk()->assertJsonCount(1, 'data');
        $local = User::factory()->create(['instance_id' => $instance->id, 'type' => 'seller']);
        $seller->update(['supervisor_id' => $local->id]);
        $local->delete();
        $this->getJson('/api/activities/assignees?business_id='.$business->id)->assertOk()->assertJsonCount(1, 'data');
    }
}
