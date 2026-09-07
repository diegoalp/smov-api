<?php
namespace Tests\Feature;
use App\Enums\UserType;
use App\Models\{Instance,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class InstanceLifecycleTest extends TestCase {
    use RefreshDatabase;
    public function test_public_registration_creates_admin_and_only_one_owned_instance(): void {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-07 12:00:00', 'America/Sao_Paulo'));
        $response=$this->postJson('/api/auth/register',[
            'name'=>'Novo admin','email'=>'new@example.com','password'=>'password123','password_confirmation'=>'password123',
        ])->assertCreated()->assertJsonPath('user.type','admin')->assertJsonPath('user.instance_id',null);
        $user=User::find($response->json('user.id'));
        Sanctum::actingAs($user);
        $this->getJson('/api/instances')->assertOk()->assertJsonCount(0,'data');
        $this->getJson('/api/funnels?instance_id=999')->assertForbidden();
        $instance=$this->postJson('/api/instances',['name'=>'Minha empresa'])->assertCreated()
            ->assertJsonPath('data.expiration_date','2026-09-14')->assertJsonPath('data.is_expired',false)->json('data');
        $this->assertDatabaseHas('users',['id'=>$user->id,'instance_id'=>$instance['id'],'type'=>'admin']);
        $this->assertDatabaseHas('instances',['id'=>$instance['id'],'owner_user_id'=>$user->id]);
        $this->postJson('/api/instances',['name'=>'Segunda empresa'])->assertStatus(409)->assertJsonPath('error.code','INSTANCE_LIMIT_REACHED');
        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/funnels')->assertOk();
    }
    public function test_signup_cannot_choose_role_tenant_or_trial_date(): void {
        $instance=Instance::create(['name'=>'Outra']);
        $base=['name'=>'Teste','email'=>'test@example.com','password'=>'password123','password_confirmation'=>'password123'];
        $this->postJson('/api/auth/register',$base+['type'=>'master'])->assertUnprocessable();
        $this->postJson('/api/auth/register',$base+['instance_id'=>$instance->id])->assertUnprocessable();
        $admin=User::factory()->create(['instance_id'=>null,'type'=>'admin']);
        Sanctum::actingAs($admin);
        $this->postJson('/api/instances',['name'=>'Teste','expiration_date'=>'2099-01-01'])->assertUnprocessable();
        $this->getJson('/api/instances/'.$instance->id)->assertNotFound();
    }
    public function test_expiration_blocks_all_tenant_routes_but_master_can_renew(): void {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-08 00:00:00','America/Sao_Paulo'));
        $instance=Instance::create(['name'=>'Expirada','expiration_date'=>'2026-09-07']);
        $admin=User::factory()->create(['instance_id'=>$instance->id,'type'=>'admin']);
        Sanctum::actingAs($admin);
        foreach (['businesses','funnels','users','activities/today','notes?business_id=1','documents?type=business&object_id=1'] as $path)
            $this->getJson('/api/'.$path)->assertStatus(423)->assertJsonPath('error.code','INSTANCE_EXPIRED');
        $this->postJson('/api/businesses',[])->assertStatus(423);
        $this->getJson('/api/auth/me')->assertOk();
        $this->getJson('/api/instances/'.$instance->id)->assertOk()->assertJsonPath('data.is_expired',true);
        $this->patchJson('/api/instances/'.$instance->id.'/activate',['expiration_date'=>'2026-10-01'])->assertForbidden();
        $this->patchJson('/api/instances/'.$instance->id,['expiration_date'=>'2026-10-01'])->assertUnprocessable();
        Sanctum::actingAs(User::factory()->create(['instance_id'=>null,'type'=>'master']));
        $this->getJson('/api/businesses?instance_id='.$instance->id)->assertStatus(423);
        $this->patchJson('/api/instances/'.$instance->id.'/activate',['expiration_date'=>'2026-09-07'])->assertUnprocessable();
        $this->patchJson('/api/instances/'.$instance->id.'/activate',['expiration_date'=>'2026-09-10T12:00:00'])->assertUnprocessable();
        $this->patchJson('/api/instances/'.$instance->id.'/activate',['expiration_date'=>'2026-09-10'])
            ->assertOk()->assertJsonPath('data.is_expired',false)->assertJsonPath('data.expiration_date','2026-09-10');
        Sanctum::actingAs($admin);
        $this->getJson('/api/funnels')->assertOk();
    }
    public function test_expiration_date_is_valid_until_end_of_local_day_and_legacy_instances_are_preserved(): void {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-07 23:59:59','America/Sao_Paulo'));
        $instance=Instance::create(['name'=>'Validade','expiration_date'=>'2026-09-07']);
        $this->assertFalse($instance->isExpired());
        $this->travel(1)->seconds();
        $this->assertTrue($instance->isExpired());
        $legacy=Instance::create(['name'=>'Existente']);
        $this->assertFalse($legacy->isExpired());
    }
    public function test_master_requires_date_and_admin_cannot_promote_themself(): void {
        $master=User::factory()->create(['instance_id'=>null,'type'=>'master']);
        Sanctum::actingAs($master);
        $this->postJson('/api/instances',['name'=>'Nova'])->assertUnprocessable();
        $this->postJson('/api/instances',['name'=>'Nova','expiration_date'=>now(config('crm.timezone'))->addDays(10)->toDateString()])->assertCreated();
        $instance=Instance::create(['name'=>'Admin']);
        $admin=User::factory()->create(['instance_id'=>$instance->id,'type'=>'admin']);
        Sanctum::actingAs($admin);
        $this->patchJson('/api/users/'.$admin->id,['type'=>'master'])->assertUnprocessable();
    }
}
