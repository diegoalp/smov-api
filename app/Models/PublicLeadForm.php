<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['public_id', 'instance_id', 'funnel_id', 'name', 'headline', 'channel', 'fields', 'active'])]
class PublicLeadForm extends Model
{
    use BelongsToInstance, SoftDeletes;

    protected function casts(): array
    {
        return ['fields' => 'array', 'active' => 'boolean'];
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    protected static function booted(): void
    {
        static::creating(function (PublicLeadForm $form): void {
            $form->public_id ??= (string) Str::uuid();
        });
    }
}
