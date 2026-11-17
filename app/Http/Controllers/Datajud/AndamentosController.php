<?php

namespace App\Http\Controllers\Datajud;

use App\Http\Controllers\Controller;
use App\Http\Resources\Datajud\ProcessoDatajudResource;
use App\Services\Datajud\DatajudClient;
use App\Services\Datajud\DatajudException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Andamentos de um processo no DataJud, pedidos pelo painel da comunicação
 * (o navegador fala com o nosso backend; o backend fala com o CNJ).
 */
class AndamentosController extends Controller
{
    public function __invoke(Request $request, DatajudClient $datajud, string $numero): JsonResponse
    {
        $tribunal = strtoupper((string) $request->query('tribunal', ''));

        if (! config('datajud.ativo') || ! $datajud->suporta($tribunal)) {
            return response()->json(['erro' => ['tipo' => 'nao_suportado', 'mensagem' => 'Andamentos ainda não disponíveis para este tribunal.']], 404);
        }

        try {
            $processos = $datajud->processos($tribunal, $numero);
        } catch (DatajudException $e) {
            Log::warning('DataJud: '.$e->getMessage(), ['tribunal' => $tribunal, 'numero' => $numero]);

            $mensagem = $e->sobrecarga
                ? 'O DataJud (CNJ) está sobrecarregado agora. Tente de novo em alguns minutos.'
                : 'O DataJud (CNJ) não respondeu. Tente de novo em alguns minutos.';

            return response()->json(['erro' => ['tipo' => 'indisponivel', 'mensagem' => $mensagem]], 503);
        }

        return response()->json(['data' => ProcessoDatajudResource::collection($processos)->resolve($request)]);
    }
}
