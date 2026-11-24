<?php

namespace App\Http\Requests\RaioX;

use App\Http\Requests\ConsultaRequest;
use App\Support\Oab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRaioXRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'oab' => Oab::normalizar((string) $this->input('oab')),
            'uf' => mb_strtoupper(trim((string) $this->input('uf'))),
            'email' => $this->filled('email') ? mb_strtolower(trim((string) $this->input('email'))) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'oab' => ['required', 'regex:'.Oab::REGEX],
            'uf' => ['required', Rule::in(ConsultaRequest::UFS)],
            // Só o admin cria em nome de outra conta.
            'email' => ['nullable', 'email', 'exists:users,email'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'oab.required' => 'Informe o número da OAB.',
            'oab.regex' => 'Número da OAB inválido.',
            'uf.*' => 'Escolha a UF da OAB.',
            'email.exists' => 'Não há conta com este e-mail.',
        ];
    }
}
