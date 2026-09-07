<?php
namespace App\Http\Controllers\Api;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\InstanceRequests\{StoreInstanceRequest,UpdateInstanceRequest};
use App\Http\Resources\InstanceResource;
use App\Models\{Instance,User};
use App\Support\ApiError;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\{DB,Storage};
class InstanceController extends Controller {
    public function index() {
        $user = request()->user();
        $query = Instance::query()->latest();
        if ($user->type !== UserType::Master) $query->whereKey($user->instance_id ?? 0);
        return InstanceResource::collection($query->paginate());
    }
    public function store(StoreInstanceRequest $request) {
        $data = $request->validated();
        $instance = DB::transaction(function () use ($request,$data) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $master = $user->type === UserType::Master;
            if (!$master && ($user->instance_id !== null || Instance::withTrashed()->where('owner_user_id',$user->id)->exists())) {
                throw new HttpResponseException(ApiError::response(409,'INSTANCE_LIMIT_REACHED','Cada usuário pode criar apenas uma instância.'));
            }
            $instance = Instance::create([
                'name'=>$data['name'],
                'expiration_date'=>$master ? ($data['expiration_date'] ?? now(config('crm.timezone'))->addDays(config('crm.initial_instance_days'))->toDateString())
                    : now(config('crm.timezone'))->addDays(config('crm.initial_instance_days'))->toDateString(),
                'owner_user_id'=>$master ? null : $user->id,
                'primary_color'=>$data['primaryColor'] ?? null,'secondary_color'=>$data['secondaryColor'] ?? null,
                'accent_color'=>$data['accentColor'] ?? null,'primary_text_color'=>$data['primaryTextColor'] ?? null,
            ]);
            if (!$master) $user->update(['instance_id'=>$instance->id,'type'=>UserType::Admin]);
            return $instance;
        });
        return (new InstanceResource($instance))->response()->setStatusCode(201);
    }
    public function show(Instance $instance) {
        $this->access($instance);
        return new InstanceResource($instance);
    }
    public function update(UpdateInstanceRequest $request, Instance $instance) {
        $this->access($instance);
        abort_unless(in_array($request->user()->type,[UserType::Admin,UserType::Master],true),403);
        if ($instance->isExpired() && $request->user()->type !== UserType::Master)
            return ApiError::response(423,'INSTANCE_EXPIRED','Somente o master pode reativar a instância.');
        $instance->update($request->validated());
        return new InstanceResource($instance->refresh());
    }
    public function activate(Request $request, Instance $instance) {
        abort_unless($request->user()->type === UserType::Master,403);
        $data=$request->validate(['expiration_date'=>['required','date_format:Y-m-d','after_or_equal:'.now(config('crm.timezone'))->toDateString()]]);
        $instance->update($data);
        return new InstanceResource($instance->refresh());
    }
    public function destroy(Instance $instance) {
        abort_unless(request()->user()->type === UserType::Master,403);
        Storage::disk('public')->deleteDirectory("instances/{$instance->id}");
        $instance->delete();
        return response()->noContent();
    }
    private function access(Instance $instance): void {
        abort_unless(request()->user()->type === UserType::Master || request()->user()->instance_id === $instance->id,404);
    }
}
