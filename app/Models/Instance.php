<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'logo_url', 'primary_color', 'secondary_color', 'accent_color', 'primary_text_color', 'expiration_date', 'owner_user_id'])]
class Instance extends Model
{
    use SoftDeletes;
    protected function casts(): array { return ['expiration_date' => 'date']; }
    public function isExpired(): bool {
        return $this->expiration_date !== null && $this->expiration_date->toDateString() < now(config('crm.timezone'))->toDateString();
    }


    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function funnels(): HasMany
    {
        return $this->hasMany(Funnel::class);
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }
}
