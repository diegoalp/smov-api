<?php
namespace App\Models;
use App\Enums\UserType;
use App\Models\Concerns\BelongsToInstance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['instance_id', 'business_id', 'user_id', 'activity_type_id', 'title', 'description', 'scheduled_at', 'status', 'completed_at'])]
class Activity extends Model {
    use BelongsToInstance;
    protected function casts(): array { return ['scheduled_at' => 'datetime', 'completed_at' => 'datetime']; }
    public function business(): BelongsTo { return $this->belongsTo(Business::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function activityType(): BelongsTo { return $this->belongsTo(ActivityType::class); }
    public function scopeVisibleTo(Builder $query, User $user, int $instanceId): Builder {
        $query->where('instance_id', $instanceId);
        if (!in_array($user->type, [UserType::Admin, UserType::Master], true)) {
            $query->where(function (Builder $query) use ($user, $instanceId) {
                $query->where('user_id', $user->id)->orWhereHas('user', fn (Builder $owners) =>
                    $owners->where('instance_id', $instanceId)->where('supervisor_id', $user->id));
            });
        }
        return $query;
    }
}
