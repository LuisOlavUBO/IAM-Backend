<?php

namespace App\Domains\Aplicaciones\Features\ReactivarAplicacion;

use Illuminate\Http\JsonResponse;

/**
 * POST /api/iam/v1/aplicaciones/{nombre}/reactivacion. Only translates HTTP: 200 {activa: true}.
 */
final class ReactivarAplicacionEndpoint
{
    public function __invoke(string $nombre, ReactivarAplicacionCommandHandler $reactivar): JsonResponse
    {
        $reactivar($nombre);

        return response()->json(['data' => ['activa' => true]]);
    }
}
