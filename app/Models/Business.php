<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'instance_id', 'client_id', 'user_id', 'category_id', 'product_id',
    'funnel_id', 'stage_id', 'status', 'value', 'notes', 'lead_source_id',
    'disposition_id', 'priority', 'due_at', 'loss_reason', 'custom_data',
])]
class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Business $business): void {
            if ($business->exists && ! $business->isDirty('stage_id')) {
                return;
            }

            $stage = $business->stage()->first();
            $business->expiration_date = null;

            if ($stage?->duration !== null && $stage->duration > 0) {
                $business->expiration_date = match ($stage->duration_unit) {
                    'horas' => now()->addHours($stage->duration),
                    'dias' => now()->addDays($stage->duration),
                    default => null,
                };
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'value' => 'integer',
            'due_at' => 'datetime',
            'expiration_date' => 'datetime',
            'custom_data' => 'array',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BusinessEvent::class)->latest();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BusinessMessage::class)->oldest();
    }
}
