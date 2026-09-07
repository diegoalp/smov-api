<?php

namespace App\Http\Requests\ActivityTypeRequests;

use App\Enums\UserType;
use App\Http\Requests\Concerns\UsesAuthenticatedInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityTypeRequest extends FormRequest
{
    use UsesAuthenticatedInstance;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->instance_id !== null || $user->type === UserType::Master);
    }

    public function rules(): array
    {
        return [
            'instance_id' => ['required', 'integer', 'exists:instances,id'],
            'activity_type' => ['required', 'string', 'max:30', Rule::unique('activity_types')->where('instance_id', $this->integer('instance_id'))],
            'funnel_ids' => ['sometimes', 'array'],
            'funnel_ids.*' => ['integer', 'distinct', Rule::exists('funnels', 'id')->where('instance_id', $this->integer('instance_id'))->whereNull('deleted_at')]
        ];
    }

    public function messages(): array
    {
        return [
            'activity_type.required' => 'O tipo de atividade é obrigatório.',
            'activity_type.unique' => 'Esta atividade já foi cadastrada para esta instância.',
            'activity_type.max' => 'O tipo de atividade não pode ter mais de 30 caracteres.',
            'funnel_ids.array' => 'Selecione uma lista de funis.',
            'funnel_ids.*.exists' => 'Um dos funis selecionados não está disponível nesta instância.',
            'funnel_ids.*.distinct' => 'Não repita um funil na seleção.',
            'instance_id.required' => 'A instância é obrigatória.',
            'instance_id.exists' => 'A instância selecionada não existe.'
        ];
    }
}
