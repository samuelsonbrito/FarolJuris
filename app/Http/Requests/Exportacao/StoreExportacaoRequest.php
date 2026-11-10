<?php

namespace App\Http\Requests\Exportacao;

use App\Enums\FormatoExportacao;
use App\Enums\OrigemExportacao;
use App\Http\Requests\ConsultaRequest;
use App\Models\Alerta;
use App\Support\Oab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExportacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    private function origem(): ?OrigemExportacao
    {
        return OrigemExportacao::tryFrom((string) $this->input('origem'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = (int) config('djen.exportacao.max_itens');
        $user = $this->user();

        $comum = [
            'origem' => ['required', Rule::enum(OrigemExportacao::class)],
            'formato' => ['required', Rule::enum(FormatoExportacao::class)],
            'todos' => ['required', 'boolean'],
            'filtros' => ['present', 'array'],
            'selecao' => ['exclude_if:todos,true', 'required', 'array', 'min:1', "max:{$max}"],
        ];

        return $comum + match ($this->origem()) {
            OrigemExportacao::Consulta => [
                // Mesmas regras da Consulta, aplicadas aos filtros salvos.
                'filtros.oab' => ['nullable', 'regex:'.Oab::REGEX],
                'filtros.uf' => ['nullable', Rule::in(ConsultaRequest::UFS)],
                'filtros.parte' => ['nullable', 'string', 'min:3', 'max:120'],
                'filtros.advogado' => ['nullable', 'string', 'min:3', 'max:120'],
                'filtros.processo' => ['nullable', 'digits:20'],
                'filtros.tribunal' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
                'filtros.inicio' => ['nullable', 'date_format:Y-m-d'],
                'filtros.fim' => ['nullable', 'date_format:Y-m-d'],
                'filtros.texto' => ['nullable', 'string', 'min:3', 'max:200'],
                'filtros.meio' => ['nullable', Rule::in(array_keys(ConsultaRequest::MEIOS))],
                'filtros.comunicacao' => ['nullable', 'digits_between:1,12'],
                'selecao.*.pagina' => ['required', 'integer', 'min:1', 'max:500'],
                'selecao.*.hashes' => ['required', 'array', 'min:1', 'max:100'],
                'selecao.*.hashes.*' => ['required', 'string', 'max:191'],
            ],
            OrigemExportacao::Monitoramento => [
                'filtros.alvo' => ['nullable', 'integer', Rule::exists('monitoring_targets', 'id')->where('user_id', $user?->id)],
                'filtros.status' => ['nullable', Rule::in(['nao_lidas', 'lidas', 'todas'])],
                'selecao.*' => ['integer', 'distinct'],
            ],
            default => [],
        };
    }

    public function after(): array
    {
        return [
            function (Validator $v): void {
                if ($v->errors()->isNotEmpty() || $this->boolean('todos')) {
                    return;
                }

                $max = (int) config('djen.exportacao.max_itens');

                if ($this->quantidadeSelecionada() > $max) {
                    $v->errors()->add('selecao', "Selecione no máximo {$max} comunicações por exportação.");
                }

                // Monitoramento: todos os alertas precisam ser do usuário.
                if ($this->origem() === OrigemExportacao::Monitoramento) {
                    $ids = array_map('intval', (array) $this->input('selecao'));
                    $meus = Alerta::query()->doUsuario($this->user())->whereKey($ids)->count();

                    if ($meus !== count($ids)) {
                        $v->errors()->add('selecao', 'Seleção inválida.');
                    }
                }
            },
        ];
    }

    public function quantidadeSelecionada(): int
    {
        return $this->origem() === OrigemExportacao::Consulta
            ? (int) collect((array) $this->input('selecao'))->sum(fn ($g) => count((array) ($g['hashes'] ?? [])))
            : count((array) $this->input('selecao'));
    }

    /**
     * Parâmetros normalizados a guardar em exports.parametros.
     *
     * @return array<string, mixed>
     */
    public function parametros(): array
    {
        $v = $this->validated();
        $filtros = array_filter((array) ($v['filtros'] ?? []), fn ($x) => $x !== null && $x !== '');

        return [
            'filtros' => $filtros,
            'todos' => (bool) $v['todos'],
            'selecao' => $v['todos'] ? [] : array_values($v['selecao']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'selecao.required' => 'Selecione ao menos uma comunicação.',
            'selecao.min' => 'Selecione ao menos uma comunicação.',
            'formato.*' => 'Escolha o formato: CSV, Excel ou PDF.',
            'filtros.*' => 'Filtros inválidos. Refaça a busca e tente de novo.',
        ];
    }
}
