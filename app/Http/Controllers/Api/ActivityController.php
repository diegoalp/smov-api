<?php
namespace App\Http\Controllers\Api;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\{Activity, ActivityType, Business, User};
use App\Support\{ApiError, InstanceContext};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActivityController extends Controller {
    private function query(Request $request) {
        return Activity::visibleTo($request->user(), InstanceContext::id($request))
            ->with(['activityType.funnels', 'user', 'business.client']);
    }
    public function index(Request $request) {
        $data = $request->validate([
            'business_id' => ['sometimes', 'integer'],
            'start' => ['sometimes', 'required', 'date'],
            'end' => ['required_with:start', 'date', 'after:start'],
        ]);
        $query = $this->query($request);
        if (isset($data['business_id'])) $query->where('business_id', $data['business_id']);
        if (isset($data['start'])) $query->where('scheduled_at', '>=', CarbonImmutable::parse($data['start'])->utc());
        if (isset($data['end'])) $query->where('scheduled_at', '<', CarbonImmutable::parse($data['end'])->utc());
        return ActivityResource::collection($query->orderBy('scheduled_at')->orderBy('id')->paginate(100));
    }
    public function today(Request $request) {
        $data = $request->validate(['timezone' => ['sometimes', 'timezone']]);
        $start = CarbonImmutable::now($data['timezone'] ?? config('app.timezone'))->startOfDay();
        return ActivityResource::collection($this->query($request)
            ->where('scheduled_at', '>=', $start->utc())->where('scheduled_at', '<', $start->addDay()->utc())
            ->orderBy('scheduled_at')->orderBy('id')->paginate(100));
    }
    public function show(Request $request, int $activity) {
        return new ActivityResource($this->query($request)->findOrFail($activity));
    }
    public function assignees(Request $request) {
        $data = $request->validate(['business_id' => ['required', 'integer']]);
        $business = Business::where('instance_id', InstanceContext::id($request))->findOrFail($data['business_id']);
        return response()->json(['data' => $this->allowedAssignees($request, $business)
            ->map(fn (User $user) => ['id' => $user->id, 'name' => trim($user->name.' '.$user->lastname)])->values()]);
    }
    private function allowedAssignees(Request $request, Business $business) {
        $actor = $request->user();
        $instanceId = InstanceContext::id($request);
        $owner = User::where('instance_id', $instanceId)->find($business->user_id);
        $administrator = in_array($actor->type, [UserType::Admin, UserType::Master], true);
        $supervisor = User::where('instance_id', $instanceId)->where('supervisor_id', $actor->id)->exists();
        abort_unless($administrator || $business->user_id === $actor->id || $owner?->supervisor_id === $actor->id, 403);
        $ids = [$actor->id];
        if ($administrator) {
            $ids[] = $owner?->id;
            $ids[] = $owner?->supervisor_id;
        } elseif ($supervisor) {
            $ids[] = $owner?->id;
        } else {
            $ids[] = $actor->supervisor_id;
        }
        return User::whereIn('id', array_filter(array_unique($ids)))
            ->where(function ($query) use ($actor, $instanceId) {
                $query->where('instance_id', $instanceId);
                // An unassigned master may schedule for themself in the selected instance.
                if ($actor->type === UserType::Master && $actor->instance_id === null) $query->orWhere('id', $actor->id);
            })->orderBy('name')->get();
    }
    public function store(Request $request) {
        $instanceId = InstanceContext::id($request);
        $data = $request->validate([
            'business_id' => ['required', 'integer'],
            'activity_type_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'scheduled_at' => ['required', 'date'],
            'status' => ['prohibited'], 'user_id' => ['sometimes', 'required', 'integer'],
        ]);
        $business = Business::where('instance_id', $instanceId)->findOrFail($data['business_id']);
        $assignees = $this->allowedAssignees($request, $business);
        $assigneeId = $data['user_id'] ?? $request->user()->id;
        if (!$assignees->contains('id', $assigneeId)) {
            throw ValidationException::withMessages(['user_id' => 'Este usuário não pode receber a atividade deste negócio.']);
        }
        $data['user_id'] = $assigneeId;
        $type = ActivityType::where('instance_id', $instanceId)->with('funnels')->find($data['activity_type_id']);
        if (!$type || ($type->funnels->isNotEmpty() && !$type->funnels->contains('id', $business->funnel_id))) {
            throw ValidationException::withMessages(['activity_type_id' => 'O tipo de atividade não está disponível para este funil.']);
        }
        if ($blocked = $this->blocked($request, $business)) return $blocked;
        $data['scheduled_at'] = CarbonImmutable::parse($data['scheduled_at'])->utc();
        $activity = Activity::create($data + ['instance_id' => $instanceId, 'status' => 'pending']);
        return (new ActivityResource($activity->load(['activityType.funnels', 'user', 'business.client'])))->response()->setStatusCode(201);
    }
    public function update(Request $request, int $activity) {
        $item = $this->query($request)->findOrFail($activity);
        $request->validate(['status' => ['required', Rule::in(['completed'])]]);
        if ($blocked = $this->blocked($request, $item->business)) return $blocked;
        if ($item->status !== 'completed') $item->update(['status' => 'completed', 'completed_at' => now()]);
        return new ActivityResource($item);
    }
    public function destroy(Request $request, int $activity) {
        $item = $this->query($request)->findOrFail($activity);
        if ($blocked = $this->blocked($request, $item->business)) return $blocked;
        $item->delete();
        return response()->noContent();
    }
    private function blocked(Request $request, ?Business $business) {
        $hasExpired = Business::where('instance_id', InstanceContext::id($request))
            ->where('user_id', $request->user()->id)->where('expiration_date', '<', now())->exists();
        if ($hasExpired && (!$business?->expiration_date || !$business->expiration_date->isPast())) {
            return ApiError::response(423, 'EXPIRED_BUSINESSES_PENDING', 'Regularize seus negócios expirados antes de alterar atividades de outros negócios.');
        }
        return null;
    }
}
