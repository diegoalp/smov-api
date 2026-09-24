<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrincipalInstanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_can_create_a_principal_instance_without_expiration(): void
    {
        Sanctum::actingAs(User::factory()->create(['instance_id' => null, 'type' => 'master']));

        $this->postJson('/api/instances', [
            'name' => 'Instância principal',
            'is_principal' => true,
        ])->assertCreated()
            ->assertJsonPath('data.is_principal', true)
            ->assertJsonPath('data.expiration_date', null)
            ->assertJsonPath('data.is_expired', false);

        $this->assertDatabaseHas('instances', [
            'name' => 'Instância principal',
            'is_principal' => true,
            'expiration_date' => null,
        ]);
    }

    public function test_non_master_cannot_create_a_principal_instance(): void
    {
        Sanctum::actingAs(User::factory()->create(['instance_id' => null, 'type' => 'admin']));

        $this->postJson('/api/instances', [
            'name' => 'Não autorizada',
            'is_principal' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('is_principal', 'error.details.fields');

        $this->assertDatabaseMissing('instances', ['name' => 'Não autorizada']);
    }

    public function test_master_can_promote_and_relegate_a_principal_instance(): void
    {
        $instance = Instance::create([
            'name' => 'Instância comum',
            'expiration_date' => now()->addDays(7)->toDateString(),
        ]);
        Sanctum::actingAs(User::factory()->create(['instance_id' => null, 'type' => 'master']));

        $this->patchJson("/api/instances/{$instance->id}", [
            'is_principal' => true,
        ])->assertOk()
            ->assertJsonPath('data.is_principal', true)
            ->assertJsonPath('data.expiration_date', null);

        $this->assertDatabaseHas('instances', [
            'id' => $instance->id,
            'is_principal' => true,
            'expiration_date' => null,
        ]);

        $this->patchJson("/api/instances/{$instance->id}", [
            'is_principal' => false,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('expiration_date', 'error.details.fields');

        $expirationDate = now()->addDays(10)->toDateString();
        $this->patchJson("/api/instances/{$instance->id}", [
            'is_principal' => false,
            'expiration_date' => $expirationDate,
        ])->assertOk()
            ->assertJsonPath('data.is_principal', false)
            ->assertJsonPath('data.expiration_date', $expirationDate);
    }

    public function test_principal_instance_is_not_expired_or_blocked(): void
    {
        $instance = Instance::create([
            'name' => 'Instância permanente',
            'is_principal' => true,
        ]);
        $admin = User::factory()->create(['instance_id' => $instance->id, 'type' => 'admin']);
        Sanctum::actingAs($admin);

        $this->travelTo(now()->addYear());

        $this->assertFalse($instance->fresh()->isExpired());
        $this->getJson('/api/funnels')->assertOk();
    }
}
