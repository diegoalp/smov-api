<?php

namespace App\Http\Requests\UserRequests;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        $instanceId = $this->integer('instance_id') ?: $this->user()?->instance_id;

        return [
            'supervisor_id' => [Rule::prohibitedIf(!in_array($this->user()?->type, [UserType::Admin, UserType::Master], true)), 'sometimes', 'nullable', 'integer', Rule::notIn([$this->route('user')?->id]),
                Rule::exists('users', 'id')->where('instance_id', $this->user()?->instance_id ?? $instanceId)->whereNull('deleted_at')],

            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'lastname' => ['sometimes', 'nullable', 'string', 'max:255'],
            'birthdate' => ['sometimes', 'nullable', 'date', 'before:today'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'password' => ['sometimes', 'required', 'confirmed', Password::min(8)],
            'type' => ['sometimes', Rule::in($this->user()?->type === UserType::Master ? ['master','admin','seller'] : ['admin','seller'])],
            'instance_id' => ['sometimes', 'nullable', 'integer', 'exists:instances,id', 'required_if:type,seller,admin'],
            'team_id' => ['sometimes', 'nullable', 'integer', Rule::exists('teams', 'id')->where('instance_id', $instanceId)],
            'funnel_id' => ['sometimes', 'nullable', 'integer', Rule::exists('funnels', 'id')->where('instance_id', $instanceId)],
        ];
    }
}
