<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'fullname', 'type', 'birthdate', 'registration', 'rg', 'street', 'district', 'city',
    'state', 'zipcode', 'gender', 'extra',
])]
class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'extra' => 'array',
        ];
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
        return $query->whereHas('businesses', fn ($query) => $query->where('instance_id', $instanceId));
    }
}
