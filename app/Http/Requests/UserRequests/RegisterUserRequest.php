<?php

namespace App\Http\Requests\UserRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'lastname' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'instance_id' => ['prohibited'],
            'type' => ['prohibited'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function atributes(): array
    {
        return [
            'name' => 'Nome',
            'lastname' => 'Sobrenome',
            'birthdate' => 'Data de nascimento',
            'instance_id' => 'ID da instância',
            'email' => 'E-mail',
            'password' => 'Senha',
        ];
    }

    public function labels(): array
    {
        return [
            'name' => 'Nome',
            'lastname' => 'Sobrenome',
            'birthdate' => 'Data de nascimento',
            'instance_id' => 'ID da instância',
            'email' => 'E-mail',
            'password' => 'Senha',
        ];
    }

    public function messages(): array
    {
        return [
            'instance_id.require_if' => 'O campo ID da instância é obrigatório para usuários do tipo seller ou admin.',
            'unique' => 'O campo :attribute informado já está em uso.',
            'required' => 'O campo :attribute é obrigatório.',
            'email' => 'O campo :attribute deve ser um endereço de e-mail válido.',
            'confirmed' => 'O campo :attribute não corresponde à confirmação.',
            'before' => 'O campo :attribute deve ser uma data anterior a hoje.',
            'string' => 'O campo :attribute deve ser uma string.',
            'max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'min' => 'O campo :attribute deve ter pelo menos :min caracteres.',
        ];
    }
}
