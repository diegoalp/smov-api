<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Support\{BusinessContentAccess, InstanceContext};
use Illuminate\Http\Request;
class NoteController extends Controller {
    public function index(Request $request) {
        $data = $request->validate(['business_id' => ['required','integer']]);
        $business = BusinessContentAccess::resolve($request, $data['business_id']);
        return response()->json(Note::where('instance_id', $business->instance_id)->where('business_id', $business->id)
            ->with('user:id,name,lastname')->latest()->paginate(100));
    }
    public function store(Request $request) {
        $data = $request->validate(['business_id' => ['required','integer'], 'body' => ['required','string','max:2000']]);
        $business = BusinessContentAccess::resolve($request, $data['business_id'], true);
        $note = Note::create($data + ['instance_id' => $business->instance_id, 'user_id' => $request->user()->id]);
        return response()->json(['data' => $note->load('user:id,name,lastname')], 201);
    }
    public function destroy(Request $request, int $note) {
        $item = Note::where('instance_id', InstanceContext::id($request))->findOrFail($note);
        BusinessContentAccess::resolve($request, $item->business_id, true);
        $item->delete();
        return response()->noContent();
    }
}
