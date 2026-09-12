<?php

namespace Tests\Feature;

use App\Models\{Business, Category, Client, Funnel, Instance, Stage, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessExpirationAccessTest extends TestCase
{
    use RefreshDatabase;

    private function business(User $user, ?string $expiration): Business
    {
        $funnel = Funnel::create(['instance_id' => $user->instance_id, 'name' => 'Funil '.Business::count()]);
        $stage = Stage::create(['funnel_id' => $funnel->id, 'name' => 'Entrada', 'position' => 1]);
        $category = Category::create(['instance_id' => $user->instance_id, 'name' => 'Categoria '.Business::count()]);
        $category->funnels()->attach($funnel);
        $client = Client::create(['fullname' => 'Cliente', 'type' => 'individual', 'registration' => fake()->unique()->numerify('###########')]);
        $business = Business::create([
            'instance_id' => $user->instance_id, 'client_id' => $client->id,
            'user_id' => $user->id, 'category_id' => $category->id, 'product_id' => null,
            'funnel_id' => $funnel->id, 'stage_id' => $stage->id, 'status' => 1, 'value' => 0,
        ]);
        $business->forceFill(['expiration_date' => $expiration])->save();
        return $business;
    }

    public function test_owner_can_only_access_expired_businesses_until_resolved(): void
    {
        $this->travelTo(now()->startOfSecond());
        $instance = Instance::create(['name' => 'A']);
        $user = User::factory()->create(['instance_id' => $instance->id, 'type' => 'admin']);
        Sanctum::actingAs($user);
        $expired = $this->business($user, now()->subSecond()->toDateTimeString());
        $otherExpired = $this->business($user, now()->subMinute()->toDateTimeString());
        $future = $this->business($user, now()->addHour()->toDateTimeString());
        $undated = $this->business($user, null);

        $this->getJson('/api/businesses?stage_id='.$expired->stage_id)->assertOk();
        foreach ([$expired, $otherExpired] as $business) {
            $this->getJson('/api/businesses/'.$business->id)->assertOk();
            $this->patchJson('/api/businesses/'.$business->id, ['notes' => 'Regularizando'])->assertOk();
        }
        foreach ([$future, $undated] as $business) {
            $this->getJson('/api/businesses/'.$business->id)->assertStatus(423);
            $this->patchJson('/api/businesses/'.$business->id, ['notes' => 'Bloqueado'])
                ->assertStatus(423)->assertJsonPath('error.code', 'EXPIRED_BUSINESSES_PENDING');
            $this->deleteJson('/api/businesses/'.$business->id)->assertStatus(423);
            $this->postJson('/api/businesses/'.$business->id.'/events', ['title' => 'Nota'])->assertStatus(423);
        }
        $this->postJson('/api/businesses', [])->assertStatus(423);
        $expired->forceFill(['expiration_date' => null])->save();
        $this->patchJson('/api/businesses/'.$future->id, ['notes' => 'Ainda bloqueado'])->assertStatus(423);
        $otherExpired->forceFill(['expiration_date' => null])->save();
        $this->patchJson('/api/businesses/'.$future->id, ['notes' => 'Liberado'])->assertOk();
    }

    public function test_other_owners_and_instances_do_not_lock_user_and_equal_time_is_not_expired(): void
    {
        $this->travelTo(now()->startOfSecond());
        $instance = Instance::create(['name' => 'A']);
        $user = User::factory()->create(['instance_id' => $instance->id, 'type' => 'admin']);
        $other = User::factory()->create(['instance_id' => $instance->id, 'type' => 'admin']);
        $this->business($other, now()->subHour()->toDateTimeString());
        $business = $this->business($user, now()->toDateTimeString());
        Sanctum::actingAs($user);
        $this->getJson('/api/businesses/'.$business->id)->assertOk();
        $this->patchJson('/api/businesses/'.$business->id, ['notes' => 'Permitido'])->assertOk();

        $foreignInstance = Instance::create(['name' => 'B']);
        $foreignUser = User::factory()->create(['instance_id' => $foreignInstance->id, 'type' => 'admin']);
        $foreign = $this->business($foreignUser, now()->subHour()->toDateTimeString());
        $foreign->update(['user_id' => $user->id]);
        $this->patchJson('/api/businesses/'.$business->id, ['notes' => 'Outra instância não bloqueia'])->assertOk();
    }
}
