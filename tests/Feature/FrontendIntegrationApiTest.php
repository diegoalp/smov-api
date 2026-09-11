<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FrontendIntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_field_contract_validates_model_conditions_and_select_options(): void
    {
        $instance = Instance::create(['name' => 'Empresa de campos']);
        Sanctum::actingAs(User::factory()->create(['instance_id' => $instance->id]));

        $this->postJson('/api/custom-fields', [
            'label' => 'Banco',
            'section' => 'business',
            'type' => 'select',
        ])->assertUnprocessable()->assertJsonValidationErrors('options', 'error.details.fields');

        $this->postJson('/api/custom-fields', [
            'label' => 'Banco',
            'section' => 'invalid-section',
            'type' => 'select',
            'options' => ['Banco do Brasil'],
        ])->assertUnprocessable()->assertJsonValidationErrors('section', 'error.details.fields');

        $sectionId = $this->postJson('/api/custom-field-sections', [
            'name' => 'Dados financeiros',
            'section' => 'business',
            'position' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Dados financeiros')
            ->assertJsonPath('data.position', 2)
            ->json('data.id');

        $this->postJson('/api/custom-field-sections', [
            'name' => 'Dados financeiros',
            'section' => 'business',
        ])->assertUnprocessable()->assertJsonValidationErrors('name', 'error.details.fields');

        $this->postJson('/api/custom-fields', [
            'label' => 'Banco',
            'section' => 'business',
            'custom_field_section_id' => $sectionId,
            'position' => 1,
            'type' => 'select',
            'options' => ['Banco do Brasil'],
        ])->assertCreated()
            ->assertJsonPath('data.custom_field_section_id', $sectionId)
            ->assertJsonPath('data.position', 1);
    }

    public function test_repeatable_custom_field_persists_and_validates_sub_fields(): void
    {
        $instance = Instance::create(['name' => 'Empresa de listas']);
        Sanctum::actingAs(User::factory()->create(['instance_id' => $instance->id]));

        $this->postJson('/api/custom-fields', [
            'label' => 'Contratos', 'section' => 'business', 'type' => 'group',
            'sub_fields' => [[
                'key' => 'bank', 'label' => 'Banco', 'type' => 'select',
                'required' => true, 'options' => ['Caixa', 'Itaú'], 'position' => 0,
            ], [
                'key' => 'balance', 'label' => 'Saldo devedor', 'type' => 'currency',
                'required' => true, 'options' => [], 'position' => 1,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.sub_fields.0.key', 'bank')
            ->assertJsonPath('data.sub_fields.0.options.1', 'Itaú')
            ->assertJsonPath('data.sub_fields.1.type', 'currency');

        $this->postJson('/api/custom-fields', [
            'label' => 'Lista inválida', 'section' => 'business', 'type' => 'group',
            'sub_fields' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('sub_fields', 'error.details.fields');

        $this->postJson('/api/custom-fields', [
            'label' => 'Seleção inválida', 'section' => 'business', 'type' => 'group',
            'sub_fields' => [[
                'key' => 'bank', 'label' => 'Banco', 'type' => 'select',
                'required' => true, 'options' => [], 'position' => 0,
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors('sub_fields.0.options', 'error.details.fields');
    }

    public function test_checkbox_custom_field_requires_and_persists_a_boolean_default(): void
    {
        $instance = Instance::create(['name' => 'Empresa de checkbox']);
        Sanctum::actingAs(User::factory()->create(['instance_id' => $instance->id]));

        $this->postJson('/api/custom-fields', [
            'label' => 'Autorizado', 'section' => 'business', 'type' => 'checkbox',
        ])->assertUnprocessable()->assertJsonValidationErrors('default_value', 'error.details.fields');

        $this->postJson('/api/custom-fields', [
            'label' => 'Autorizado', 'section' => 'business', 'type' => 'checkbox',
            'default_value' => false,
        ])->assertCreated()->assertJsonPath('data.default_value', false);

        $this->postJson('/api/custom-fields', [
            'label' => 'Ativo', 'section' => 'client', 'type' => 'checkbox',
            'default_value' => true,
        ])->assertCreated()->assertJsonPath('data.default_value', true);
    }

    public function test_instance_configuration_resources_are_persisted_and_isolated(): void
    {
        $firstInstance = Instance::create(['name' => 'Empresa A']);
        $secondInstance = Instance::create(['name' => 'Empresa B']);
        $firstUser = User::factory()->create(['instance_id' => $firstInstance->id]);
        $secondUser = User::factory()->create(['instance_id' => $secondInstance->id]);

        Sanctum::actingAs($firstUser);
        $sourceId = $this->postJson('/api/lead-sources', [
            'name' => 'Facebook Ads', 'type' => 'automatica', 'description' => 'Campanha',
        ])->assertCreated()->assertJsonPath('data.instance_id', $firstInstance->id)->json('data.id');
        $this->patchJson("/api/lead-sources/{$sourceId}", ['description' => 'Campanha atualizada'])
            ->assertOk()->assertJsonPath('data.description', 'Campanha atualizada');

        $category = Category::create(['instance_id' => $firstInstance->id, 'name' => 'Servidor público']);
        $this->postJson('/api/custom-fields', [
            'label' => 'Convênio', 'section' => 'business', 'type' => 'select',
            'required' => true, 'options' => ['INSS', 'SIAPE'],
            'conditions' => [['field' => 'category_id', 'operator' => 'equals', 'value' => [$category->id]]],
        ])->assertCreated()
            ->assertJsonPath('data.options.1', 'SIAPE')
            ->assertJsonPath('data.conditions.0.field', 'category_id');

        $this->postJson('/api/automation-rules', [
            'name' => 'SLA', 'trigger' => 'lead_created', 'condition' => '4 hours', 'action' => 'notify_owner',
        ])->assertCreated();

        $this->putJson('/api/branding', [
            'companyName' => 'Marca A', 'primaryColor' => '#112233',
        ])->assertOk()->assertJsonPath('data.branding.companyName', 'Marca A');

        Sanctum::actingAs($secondUser);
        $this->getJson('/api/lead-sources')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/lead-sources/{$sourceId}")->assertNotFound();
        $this->getJson('/api/custom-fields')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_operation_template_installation_uses_frontend_contract(): void
    {
        $instance = Instance::create(['name' => 'Empresa A']);
        Sanctum::actingAs(User::factory()->create(['instance_id' => $instance->id]));

        $this->putJson('/api/operation-template', ['templateId' => 'credito-consignado'])
            ->assertOk()
            ->assertJsonPath('data.templateId', 'credito-consignado');

        $this->getJson('/api/operation-template')
            ->assertOk()
            ->assertJsonPath('data.template_key', 'credito-consignado');
    }

    public function test_master_user_requests_are_scoped_to_the_selected_instance(): void
    {
        $firstInstance = Instance::create(['name' => 'Empresa A']);
        $secondInstance = Instance::create(['name' => 'Empresa B']);
        Sanctum::actingAs(User::factory()->create(['instance_id' => null, 'type' => 'master']));

        $this->postJson('/api/funnels', [
            'instance_id' => $firstInstance->id,
            'name' => 'Funil A',
        ])->assertCreated();

        $this->postJson('/api/funnels', [
            'instance_id' => $secondInstance->id,
            'name' => 'Funil B',
        ])->assertCreated();

        $this->getJson('/api/funnels?instance_id='.$firstInstance->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Funil A');

        $this->putJson('/api/branding', [
            'instance_id' => $secondInstance->id,
            'companyName' => 'Marca B',
        ])->assertOk()->assertJsonPath('data.branding.companyName', 'Marca B');
    }

    public function test_funnels_returns_an_explicit_error_when_master_user_does_not_select_an_instance(): void
    {
        Sanctum::actingAs(User::factory()->create(['instance_id' => null, 'type' => 'master']));

        $this->getJson('/api/funnels')
            ->assertUnprocessable()
            ->assertExactJson([
                'success' => false,
                'error' => [
                    'status' => 422,
                    'code' => 'INSTANCE_REQUIRED',
                    'message' => 'A valid instance must be selected.',
                ],
            ]);
    }

    public function test_product_fields_use_the_english_frontend_contract(): void
    {
        $instance = Instance::create(['name' => 'Empresa A']);
        Sanctum::actingAs(User::factory()->create(['instance_id' => $instance->id]));
        $funnelId = $this->postJson('/api/funnels', ['name' => 'Sales'])
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/api/products', [
            'title' => 'Crédito',
            'prefix' => 'CRED',
            'color' => '#123456',
            'funnel_ids' => [$funnelId],
            'fields' => [[
                'id' => 'document',
                'label' => 'Document',
                'type' => 'text',
                'required' => true,
                'subFields' => [['label' => 'Number', 'type' => 'text']],
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.fields.0.type', 'text')
            ->assertJsonPath('data.fields.0.required', true)
            ->assertJsonPath('data.fields.0.subFields.0.label', 'Number');
    }
}
