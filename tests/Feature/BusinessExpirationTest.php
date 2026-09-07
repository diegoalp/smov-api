<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Instance;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiration_tracks_entry_into_stage_and_not_other_edits(): void
    {
        $this->travelTo(now()->startOfSecond());
        $instance = Instance::create(['name' => 'Empresa']);
        $user = User::factory()->create(['instance_id' => $instance->id]);
        Sanctum::actingAs($user);
        $funnel = $this->postJson('/api/funnels', ['name' => 'Comercial'])->assertCreated()->json('data.id');
        $category = $this->postJson('/api/categories', ['name' => 'Categoria', 'funnel_ids' => [$funnel]])
            ->assertCreated()->json('data.id');
        $client = Client::create(['fullname' => 'Cliente', 'type' => 'individual', 'registration' => '11122233344']);
        $first = Stage::create(['funnel_id' => $funnel, 'name' => 'Inicial', 'position' => 1, 'duration' => 2, 'duration_unit' => 'horas']);
        $next = Stage::create(['funnel_id' => $funnel, 'name' => 'Seguinte', 'position' => 2, 'duration' => 3, 'duration_unit' => 'dias']);
        $noDuration = Stage::create(['funnel_id' => $funnel, 'name' => 'Sem prazo', 'position' => 3]);
        $partial = Stage::create(['funnel_id' => $funnel, 'name' => 'Parcial', 'position' => 4, 'duration' => 1]);

        $expected = now()->addHours(2);
        $id = $this->postJson('/api/businesses', [
            'client_id' => $client->id, 'user_id' => $user->id,
            'category_id' => $category, 'funnel_id' => $funnel, 'status' => 1,
            'expiration_date' => '2099-01-01 00:00:00',
        ])->assertCreated()->assertJsonPath('data.expiration_date', $expected->toISOString())->json('data.id');
        $this->assertTrue(Business::findOrFail($id)->expiration_date->equalTo($expected));

        $this->travel(1)->hours();
        $this->patchJson("/api/businesses/$id", ['notes' => 'Editado', 'stage_id' => $first->id])
            ->assertOk()->assertJsonPath('data.expiration_date', $expected->toISOString());
        $this->patchJson("/api/businesses/$id", ['stage_id' => $next->id])
            ->assertOk()->assertJsonPath('data.expiration_date', now()->addDays(3)->toISOString());
        $this->assertTrue(Business::findOrFail($id)->expiration_date->equalTo(now()->addDays(3)));
        $this->patchJson("/api/businesses/$id", ['stage_id' => $noDuration->id])
            ->assertOk()->assertJsonPath('data.expiration_date', null);
        $this->patchJson("/api/businesses/$id", ['stage_id' => $first->id])
            ->assertOk()->assertJsonPath('data.expiration_date', now()->addHours(2)->toISOString());
        $this->patchJson("/api/businesses/$id", ['stage_id' => $partial->id])
            ->assertOk()->assertJsonPath('data.expiration_date', null);
        $this->travelBack();
    }
}
