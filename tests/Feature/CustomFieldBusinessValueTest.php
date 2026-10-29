<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomFieldBusinessValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_value_flag_survives_create_update_show_and_list(): void
    {
        $instance = Instance::create(['name' => 'Business value tenant']);
        Sanctum::actingAs(User::factory()->create(['instance_id' => $instance->id]));

        $id = $this->postJson('/api/custom-fields', [
            'label' => 'Contrato', 'section' => 'business', 'type' => 'currency',
            'is_business_value' => true,
        ])->assertCreated()->assertJsonPath('data.is_business_value', true)->json('data.id');

        $this->assertDatabaseHas('custom_fields', ['id' => $id, 'is_business_value' => true]);
        $this->getJson("/api/custom-fields/{$id}")->assertOk()->assertJsonPath('data.is_business_value', true);
        $this->getJson('/api/custom-fields')->assertOk()->assertJsonPath('data.0.is_business_value', true);
        $this->patchJson("/api/custom-fields/{$id}", ['label' => 'Contrato atualizado'])
            ->assertOk()->assertJsonPath('data.is_business_value', true);

        $this->patchJson("/api/custom-fields/{$id}", ['is_business_value' => false])
            ->assertOk()->assertJsonPath('data.is_business_value', false);
        $this->getJson("/api/custom-fields/{$id}")->assertOk()->assertJsonPath('data.is_business_value', false);
        $this->getJson('/api/custom-fields')->assertOk()->assertJsonPath('data.0.is_business_value', false);

        $this->patchJson("/api/custom-fields/{$id}", ['is_business_value' => true])
            ->assertOk()->assertJsonPath('data.is_business_value', true);
        $this->getJson('/api/custom-fields')->assertOk()->assertJsonPath('data.0.is_business_value', true);
    }

    public function test_existing_currency_field_can_be_selected_and_type_changes_clear_the_flag(): void
    {
        $instance = Instance::create(['name' => 'Existing fields tenant']);
        Sanctum::actingAs(User::factory()->create(['instance_id' => $instance->id]));

        $id = $this->postJson('/api/custom-fields', [
            'label' => 'Contrato', 'section' => 'business', 'type' => 'currency',
        ])->assertCreated()->assertJsonPath('data.is_business_value', false)->json('data.id');

        $this->patchJson("/api/custom-fields/{$id}", ['is_business_value' => true])
            ->assertOk()->assertJsonPath('data.is_business_value', true);
        $this->getJson("/api/custom-fields/{$id}")->assertOk()->assertJsonPath('data.is_business_value', true);
        $this->patchJson("/api/custom-fields/{$id}", ['type' => 'text'])
            ->assertOk()->assertJsonPath('data.is_business_value', false);
        $this->getJson("/api/custom-fields/{$id}")->assertOk()->assertJsonPath('data.is_business_value', false);
    }

    public function test_only_currency_fields_accept_business_value_activation(): void
    {
        $instance = Instance::create(['name' => 'Validation tenant']);
        Sanctum::actingAs(User::factory()->create(['instance_id' => $instance->id]));

        $this->postJson('/api/custom-fields', [
            'label' => 'Name', 'section' => 'business', 'type' => 'text',
            'is_business_value' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_business_value', 'error.details.fields');

        $id = $this->postJson('/api/custom-fields', [
            'label' => 'Name', 'section' => 'business', 'type' => 'text',
        ])->assertCreated()->json('data.id');
        $this->patchJson("/api/custom-fields/{$id}", ['is_business_value' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('is_business_value', 'error.details.fields');
        $this->patchJson("/api/custom-fields/{$id}", ['is_business_value' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('is_business_value', 'error.details.fields');
    }

    public function test_business_value_field_is_isolated_between_tenants(): void
    {
        $first = Instance::create(['name' => 'First tenant']);
        $second = Instance::create(['name' => 'Second tenant']);
        Sanctum::actingAs(User::factory()->create(['instance_id' => $first->id]));

        $id = $this->postJson('/api/custom-fields', [
            'label' => 'Contrato', 'section' => 'business', 'type' => 'currency',
            'is_business_value' => true,
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs(User::factory()->create(['instance_id' => $second->id]));
        $this->getJson('/api/custom-fields')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/custom-fields/{$id}")->assertNotFound();
        $this->patchJson("/api/custom-fields/{$id}", ['is_business_value' => false])->assertNotFound();
        $this->assertDatabaseHas('custom_fields', ['id' => $id, 'is_business_value' => true]);
    }
}
