<?php

namespace App\Http\Requests\RaioX;

use App\Enums\RaioX\GrupoOrigem;
use App\Enums\RaioX\InstanciaDecisao;
use App\Enums\RaioX\ResultadoDecisao;
use App\Enums\RaioX\TemaRaioX;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConferirProcessoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resultado' => ['required', Rule::enum(ResultadoDecisao::class)],
            'extincaoSemMerito' => ['boolean'],
            'instancia' => ['nullable', Rule::enum(InstanciaDecisao::class)],
            'orgaoJulgador' => ['nullable', 'string', 'max:255'],
            'varaOrigem' => ['nullable', 'string', 'max:255'],
            'grupoOrigem' => ['nullable', Rule::enum(GrupoOrigem::class)],
            'tema' => ['nullable', Rule::enum(TemaRaioX::class)],
            'parteContraria' => ['nullable', 'string', 'max:255'],
            'poloCliente' => ['nullable', Rule::in(['ativo', 'passivo', 'indefinido'])],
        ];
    }
}
