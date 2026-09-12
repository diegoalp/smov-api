<?php
namespace Tests\Feature;
use App\Models\{Business, Category, Client, DocumentType, Funnel, Instance, Stage, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class BusinessContentApiTest extends TestCase {
    use RefreshDatabase;
    private function business(User $owner): Business {
        $funnel = Funnel::create(['instance_id'=>$owner->instance_id,'name'=>'Funil '.Business::count()]);
        $stage = Stage::create(['funnel_id'=>$funnel->id,'name'=>'Entrada','position'=>1]);
        $category = Category::create(['instance_id'=>$owner->instance_id,'name'=>'Categoria '.Business::count()]);
        $client = Client::create(['fullname'=>'Cliente','type'=>'individual','registration'=>fake()->unique()->numerify('###########')]);
        return Business::create(['instance_id'=>$owner->instance_id,'user_id'=>$owner->id,'client_id'=>$client->id,
            'category_id'=>$category->id,'funnel_id'=>$funnel->id,'stage_id'=>$stage->id,'status'=>1,'value'=>0]);
    }
    public function test_note_and_document_full_flow(): void {
        config(['filesystems.documents' => 's3', 'filesystems.disks.s3.url' => 'https://cdn.example.test']);
        Storage::fake('s3');
        Storage::disk('s3')->buildTemporaryUrlsUsing(
            fn (string $path, mixed $expiration, array $options = []) => 'https://s3.example.test/'.ltrim($path, '/')
        );
        $instance = Instance::create(['name'=>'A']);
        $user = User::factory()->create(['instance_id'=>$instance->id,'type'=>'seller']);
        Sanctum::actingAs($user); $business = $this->business($user);
        $this->getJson('/api/notes?business_id='.$business->id)->assertOk()->assertJsonCount(0,'data');
        $noteId = $this->postJson('/api/notes',['business_id'=>$business->id,'body'=>str_repeat('a',500)])
            ->assertCreated()->assertJsonPath('data.user.id',$user->id)->json('data.id');
        $this->getJson('/api/notes?business_id='.$business->id)->assertOk()->assertJsonCount(1,'data');
        $this->deleteJson('/api/notes/'.$noteId)->assertNoContent();
        $this->getJson('/api/documents?type=business&object_id='.$business->id)->assertOk()->assertJsonCount(0,'data');
        $type = DocumentType::create(['instance_id'=>$instance->id,'name'=>'COMPROVANTE DE ENDEREÇO']);
        $document = $this->postJson('/api/documents',['type'=>'business','object_id'=>$business->id,'document_type_id'=>$type->id,
            'file'=>UploadedFile::fake()->create('conta.pdf',10,'application/pdf')])->assertCreated()->assertJsonPath('data.title','COMPROVANTE DE ENDEREÇO')->json('data');
        $this->assertSame('s3', $document['disk']);
        $this->assertSame('documents/'.$instance->id.'/'.$business->id.'/comprovante-de-endereco.pdf', $document['file']);
        $this->assertSame('comprovante-de-endereco.pdf', $document['original_name']);
        $this->assertNotEmpty($document['file_url']);
        Storage::disk('s3')->assertExists($document['file']);
        $this->getJson('/api/documents?type=business&object_id='.$business->id)->assertOk()->assertJsonCount(1,'data');
        $this->getJson('/api/documents/'.$document['id'].'/url')->assertOk()->assertJsonPath('data.file_url', $document['file_url']);
        $this->get('/api/documents/0/download?type=business&object_id='.$business->id.'&document_name='.rawurlencode($type->name))->assertOk()->assertContent($document['download_url']);
        $this->get('/api/documents/'.$document['id'].'/download')->assertOk()->assertContent($document['download_url']);
        $this->deleteJson('/api/documents/'.$document['id'])->assertNoContent();
        Storage::disk('s3')->assertMissing($document['file']);
    }
    public function test_scoping_validation_and_expired_business_lock(): void {
        Storage::fake('local');
        $a=Instance::create(['name'=>'A']); $b=Instance::create(['name'=>'B']);
        $user=User::factory()->create(['instance_id'=>$a->id,'type'=>'seller']);
        $other=User::factory()->create(['instance_id'=>$a->id,'type'=>'seller']);
        $foreign=User::factory()->create(['instance_id'=>$b->id,'type'=>'seller']);
        $own=$this->business($user); $hidden=$this->business($other); $outside=$this->business($foreign);
        Sanctum::actingAs($user);
        $this->getJson('/api/notes?business_id='.$outside->id)->assertNotFound();
        $this->getJson('/api/documents?type=business&object_id='.$outside->id)->assertNotFound();
        $this->getJson('/api/notes?business_id='.$hidden->id)->assertForbidden();
        $this->getJson('/api/documents?type=business&object_id='.$hidden->id)->assertForbidden();
        $this->postJson('/api/notes',['business_id'=>$own->id,'body'=>''])->assertUnprocessable();
        $type=DocumentType::create(['instance_id'=>$b->id,'name'=>'RG']);
        $this->postJson('/api/documents',['type'=>'business','object_id'=>$own->id,'document_type_id'=>$type->id,
            'file'=>UploadedFile::fake()->create('rg.pdf',10,'application/pdf')])->assertUnprocessable();
        $expired=$this->business($user); $expired->forceFill(['expiration_date'=>now()->subHour()])->save();
        $this->postJson('/api/notes',['business_id'=>$own->id,'body'=>'Bloqueado'])->assertStatus(423);
        $this->postJson('/api/notes',['business_id'=>$expired->id,'body'=>'Permitido'])->assertCreated();
    }
}
