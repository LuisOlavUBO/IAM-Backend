<?php

namespace App\Domains\Aplicaciones\Features\BorrarAplicacion;

use Illuminate\Http\JsonResponse;

/**
 * DELETE /api/iam/v1/aplicaciones/{nombre}. Only translates HTTP: 200 {borrado: true}; 409 EN_USO with the
 * impact (design D1 and D3 of aplicaciones-renombrar-y-borrar).
 */
final class BorrarAplicacionEndpoint
{
    public function __invoke(string $nombre, BorrarAplicacionCommandHandler $borrar): JsonResponse
    {
        $borrar($nombre);

        return response()->json(['data' => ['borrado' => true]]);
    }
}
