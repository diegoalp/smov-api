<?php

namespace Tests\Feature;

use App\Models\{DocumentType, Instance, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentTypeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_and_validation(): void
    {
        $instance = Instance::create(['name' => 'A']);
        $other = Instance::create(['name' => 'B']);
        Sanctum::actingAs(User::factory()->create(['instance_id' => $instance->id]));
        $id = $this->postJson('/api/document-types', ['name' => 'RG', 'instance_id' => $other->id])
            ->assertCreated()->assertJsonPath('data.name', 'RG')
            ->assertJsonPath('data.instance_id', $instance->id)->json('data.id');
        $this->getJson('/api/document-types')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/document-types/'.$id)->assertOk()->assertJsonPath('data.name', 'RG');
        $this->patchJson('/api/document-types/'.$id, ['name' => 'RG'])->assertOk();
        $this->putJson('/api/document-types/'.$id, ['name' => 'CPF', 'instance_id' => $other->id])
            ->assertOk()->assertJsonPath('data.name', 'CPF')->assertJsonPath('data.instance_id', $instance->id);
        $this->postJson('/api/document-types', ['name' => 'CPF'])->assertUnprocessable();
        $this->postJson('/api/document-types', ['name' => ''])->assertUnprocessable();
        $this->postJson('/api/document-types', ['name' => str_repeat('x', 101)])->assertUnprocessable();
        $this->deleteJson('/api/document-types/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('document_types', ['id' => $id]);
    }

    public function test_tenant_isolation_and_names_can_repeat_across_instances(): void
    {
        $instance = Instance::create(['name' => 'A']);
        $other = Instance::create(['name' => 'B']);
        $foreign = DocumentType::create(['name' => 'RG', 'instance_id' => $other->id]);
        Sanctum::actingAs(User::factory()->create(['instance_id' => $instance->id]));
        $this->getJson('/api/document-types')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/document-types/'.$foreign->id)->assertNotFound();
        $this->patchJson('/api/document-types/'.$foreign->id, ['name' => 'CPF'])->assertNotFound();
        $this->deleteJson('/api/document-types/'.$foreign->id)->assertNotFound();
        $this->postJson('/api/document-types', ['name' => 'RG'])->assertCreated();
    }

    public function test_unassigned_user_cannot_create_and_master_must_select_instance(): void
    {
        $instance = Instance::create(['name' => 'A']);
        Sanctum::actingAs(User::factory()->create(['type' => 'seller', 'instance_id' => null]));
        $this->postJson('/api/document-types', ['name' => 'RG', 'instance_id' => $instance->id])->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['type' => 'master', 'instance_id' => null]));
        $this->postJson('/api/document-types', ['name' => 'RG'])->assertUnprocessable();
        $this->postJson('/api/document-types', ['name' => 'RG', 'instance_id' => $instance->id])
            ->assertCreated()->assertJsonPath('data.instance_id', $instance->id);
    }
}
