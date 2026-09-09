<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_instance_crud(): void
    {
        Sanctum::actingAs(User::factory()->create(['instance_id' => null, 'type' => 'master']));

        $instance = $this->postJson('/api/instances', [
            'name' => 'Empresa Postman',
            'expiration_date' => now(config('crm.timezone'))->addDays(7)->toDateString(),
            'primaryColor' => '#625751',
            'secondaryColor' => '#0f766e',
            'accentColor' => '#f59e0b',
            'primaryTextColor' => '#ffffff',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Empresa Postman')
            ->assertJsonPath('data.branding.logoDataUrl', null)
            ->assertJsonPath('data.branding.primaryColor', '#625751')
            ->json('data');

        $this->getJson('/api/instances')
            ->assertOk()
            ->assertJsonFragment(['id' => $instance['id']]);

        $this->getJson("/api/instances/{$instance['id']}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Empresa Postman');

        $this->patchJson("/api/instances/{$instance['id']}", [
            'name' => 'Empresa Atualizada',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Empresa Atualizada');

        $this->deleteJson("/api/instances/{$instance['id']}")
            ->assertNoContent();

        $this->assertSoftDeleted('instances', ['id' => $instance['id']]);
    }

    public function test_tenant_user_cannot_access_another_instance(): void
    {
        $ownInstance = Instance::create(['name' => 'Empresa A']);
        $otherInstance = Instance::create(['name' => 'Empresa B']);
        Sanctum::actingAs(User::factory()->create(['instance_id' => $ownInstance->id]));

        $this->getJson('/api/instances')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownInstance->id);

        $this->getJson("/api/instances/{$otherInstance->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_instance_name_must_be_unique(): void
    {
        Instance::create(['name' => 'Empresa existente', 'expiration_date' => now(config('crm.timezone'))->addDays(7)->toDateString()]);
        Sanctum::actingAs(User::factory()->create(['instance_id' => null, 'type' => 'master']));

        $this->postJson('/api/instances', ['name' => 'Empresa existente', 'expiration_date' => now(config('crm.timezone'))->addDays(7)->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name', 'error.details.fields');
    }

    public function test_branding_is_persisted_on_the_active_instance(): void
    {
        Storage::fake('public');
        $instance = Instance::create(['name' => 'Marca original']);
        Sanctum::actingAs(User::factory()->create(['instance_id' => $instance->id]));

        $this->putJson('/api/branding', [
            'companyName' => 'Marca personalizada',
            'primaryColor' => '#112233',
            'secondaryColor' => '#445566',
            'accentColor' => '#778899',
            'primaryTextColor' => '#ffffff',
        ])->assertOk()
            ->assertJsonPath('data.branding.companyName', 'Marca personalizada')
            ->assertJsonPath('data.branding.primaryColor', '#112233');

        $this->assertDatabaseHas('instances', [
            'id' => $instance->id,
            'name' => 'Marca personalizada',
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
            'accent_color' => '#778899',
            'primary_text_color' => '#ffffff',
        ]);

        $this->post('/api/branding/logo', [
            'logo' => UploadedFile::fake()->image('marca.png', 200, 100),
        ])->assertOk()
            ->assertJsonPath('data.branding.companyName', 'Marca personalizada');

        $logoPath = $instance->refresh()->logo_url;
        $this->assertNotNull($logoPath);
        $this->assertStringStartsWith("instances/{$instance->id}/branding/", $logoPath);
        $this->assertStringNotContainsString('data:image', $logoPath);
        Storage::disk('public')->assertExists($logoPath);

        $this->getJson('/api/branding')
            ->assertOk()
            ->assertJsonPath('data.branding.logoDataUrl', rtrim(config('app.url'), '/')."/files/{$logoPath}")
            ->assertJsonPath('data.branding.secondaryColor', '#445566');

        $this->get("/files/{$logoPath}")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->deleteJson('/api/branding/logo')
            ->assertOk()
            ->assertJsonPath('data.branding.logoDataUrl', null);

        $this->assertNull($instance->refresh()->logo_url);
        Storage::disk('public')->assertMissing($logoPath);
    }
}
