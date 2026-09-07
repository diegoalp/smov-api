<?php

namespace App\Http\Requests\DocumentTypeRequests;

use App\Enums\UserType;
use App\Http\Requests\Concerns\UsesAuthenticatedInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentTypeRequest extends FormRequest
{
    use UsesAuthenticatedInstance;

    public function authorize(): bool
    {
        return $this->user() !== null
            && ($this->user()->instance_id !== null || $this->user()->type === UserType::Master);
    }

    public function rules(): array
    {
        $documentType = $this->route('document_type');

        return [
            'instance_id' => ['required', 'integer', 'exists:instances,id'],
            'name' => ['required', 'string', 'max:100',
                Rule::unique('document_types', 'name')
                    ->where('instance_id', $this->integer('instance_id'))
                    ->ignore($documentType?->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do tipo de documento é obrigatório.',
            'name.max' => 'O nome deve ter no máximo 100 caracteres.',
            'name.unique' => 'Este tipo de documento já existe nesta instância.',
        ];
    }
}
