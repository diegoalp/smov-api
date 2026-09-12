<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Document,DocumentType};
use App\Support\{BusinessContentAccess, InstanceContext};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class DocumentController extends Controller {
    private function temporaryUrl(Document $item): string {
        return Storage::disk('s3')->temporaryUrl(
            ltrim($item->file, '/'),
            now()->addMinutes(10)
        );
    }
    private function payload(Document $item, Request $request): array {
        $disk = $item->disk ?: 's3';
        $downloadUrl = $this->temporaryUrl($item);

        return [
            'id' => $item->id, 'title' => $item->title, 'document_type_id' => $item->document_type_id,
            'type' => 'business', 'object_id' => $item->business_id, 'file' => $item->file, 'disk' => $disk,
            'original_name' => $item->original_name, 'mime_type' => $item->mime_type,
            'file_url' => $downloadUrl,
            'download_url' => $downloadUrl, 'created_at' => $item->created_at,
        ];
    }
    public function index(Request $request) {
        $data = $request->validate(['type' => ['required', Rule::in(['business'])], 'object_id' => ['required','integer']]);
        $business = BusinessContentAccess::resolve($request, $data['object_id']);
        $items = Document::where('instance_id', $business->instance_id)->where('business_id', $business->id)->latest()->paginate(100);
        $items->through(fn (Document $item) => $this->payload($item, $request));
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
        $documentType = DocumentType::findOrFail($data['document_type_id']);
        $file = $request->file('file');
        $disk = config('filesystems.documents', 's3');
        $directory = 'documents/'.$instanceId.'/'.$business->id;
        $baseName = Str::slug($documentType->name) ?: 'documento';
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        $fileName = $extension ? $baseName.'.'.$extension : $baseName;
        $path = $directory.'/'.$fileName;
        for ($suffix = 2; Storage::disk($disk)->exists($path); $suffix++) {
            $fileName = $extension ? $baseName.'-'.$suffix.'.'.$extension : $baseName.'-'.$suffix;
            $path = $directory.'/'.$fileName;
        }
        $path = $file->storeAs($directory, $fileName, $disk);
        abort_unless($path, 500);
        try {
            $item = Document::create([
                'instance_id' => $instanceId, 'business_id' => $business->id, 'user_id' => $request->user()->id,
                'document_type_id' => $documentType->id, 'title' => $documentType->name,
                'file' => $path, 'disk' => $disk, 'original_name' => $fileName, 'mime_type' => $file->getMimeType(),
            ]);
        } catch (\Throwable $error) { Storage::disk($disk)->delete($path); throw $error; }
        return response()->json(['data' => $this->payload($item, $request)], 201);
    }
    private function find(Request $request, int $id, bool $write = false): Document {
        $item = Document::where('instance_id', InstanceContext::id($request))->findOrFail($id);
        BusinessContentAccess::resolve($request, $item->business_id, $write);
        return $item;
    }
    private function findForDownload(Request $request, int $id): Document {
        $businessId = $request->integer('object_id') ?: $request->integer('id');
        $documentName = trim((string) ($request->input('document_name') ?: $request->input('title')));

        if ($request->input('type') === 'business' && $businessId > 0 && $documentName !== '') {
            $business = BusinessContentAccess::resolve($request, $businessId);
            $item = Document::where('instance_id', $business->instance_id)
                ->where('business_id', $business->id)
                ->where('title', $documentName)
                ->latest()
                ->first();

            if ($item) {
                return $item;
            }
        }

        return $this->find($request, $id);
    }
    public function download(Request $request, int $document) {
        $item = $this->findForDownload($request, $document);

        $disk = $item->disk ?: 'local';

        abort_unless(Storage::disk($disk)->exists($item->file), 404);

        return $this->temporaryUrl($item);
    }
    public function url(Request $request, int $document) {
        $item = $this->find($request, $document);
        return response()->json(['data' => $this->payload($item, $request)]);
    }
    public function destroy(Request $request, int $document) {
        $item = $this->find($request, $document, true);
        $path = $item->file; $disk = $item->disk ?: 's3'; $item->delete();
        Storage::disk($disk)->delete($path);
        return response()->noContent();
    }
}
