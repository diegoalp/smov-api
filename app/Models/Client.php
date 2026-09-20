<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'instance_id', 'fullname', 'type', 'birthdate', 'registration', 'rg', 'street', 'district',
    'city', 'state', 'zipcode', 'gender', 'extra',
])]
class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'instance_id' => 'integer',
            'extra' => 'array',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    public function phones(): HasMany
    {
        return $this->hasMany(Phone::class);
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    public function scopeAccessibleByInstance($query, int $instanceId)
    {
        return $query->where('instance_id', $instanceId);
    }
}
