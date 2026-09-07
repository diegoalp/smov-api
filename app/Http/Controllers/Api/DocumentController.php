<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Document,DocumentType};
use App\Support\{BusinessContentAccess, InstanceContext};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
class DocumentController extends Controller {
    private function payload(Document $item): array {
        return [
            'id' => $item->id, 'title' => $item->title, 'document_type_id' => $item->document_type_id,
            'type' => 'business', 'object_id' => $item->business_id, 'file' => $item->file,
            'original_name' => $item->original_name, 'mime_type' => $item->mime_type,
            'file_url' => '/api/documents/'.$item->id.'/download', 'created_at' => $item->created_at,
        ];
    }
    public function index(Request $request) {
        $data = $request->validate(['type' => ['required', Rule::in(['business'])], 'object_id' => ['required','integer']]);
        $business = BusinessContentAccess::resolve($request, $data['object_id']);
        $items = Document::where('instance_id', $business->instance_id)->where('business_id', $business->id)->latest()->paginate(100);
        $items->through(fn (Document $item) => $this->payload($item));
        return response()->json($items);
    }
    public function store(Request $request) {
        $instanceId = InstanceContext::id($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['business'])], 'object_id' => ['required','integer'],
            'document_type_id' => ['required','integer',Rule::exists('document_types','id')->where('instance_id',$instanceId)],
            'file' => ['required','file','mimes:pdf,jpg,jpeg,png,gif,webp','max:10240'],
        ]);
        $business = BusinessContentAccess::resolve($request, $data['object_id'], true);
        $file = $request->file('file');
        $path = $file->store('documents/'.$instanceId, 'local');
        abort_unless($path, 500);
        try {
            $item = Document::create([
                'instance_id' => $instanceId, 'business_id' => $business->id, 'user_id' => $request->user()->id,
                'document_type_id' => $data['document_type_id'], 'title' => DocumentType::findOrFail($data['document_type_id'])->name,
                'file' => $path, 'original_name' => basename($file->getClientOriginalName()), 'mime_type' => $file->getMimeType(),
            ]);
        } catch (\Throwable $error) { Storage::disk('local')->delete($path); throw $error; }
        return response()->json(['data' => $this->payload($item)], 201);
    }
    private function find(Request $request, int $id, bool $write = false): Document {
        $item = Document::where('instance_id', InstanceContext::id($request))->findOrFail($id);
        BusinessContentAccess::resolve($request, $item->business_id, $write);
        return $item;
    }
    public function download(Request $request, int $document) {
        $item = $this->find($request, $document);
        abort_unless(Storage::disk('local')->exists($item->file), 404);
        return Storage::disk('local')->download($item->file, $item->original_name, ['Content-Type' => $item->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }
    public function destroy(Request $request, int $document) {
        $item = $this->find($request, $document, true);
        $path = $item->file; $item->delete();
        Storage::disk('local')->delete($path);
        return response()->noContent();
    }
}
