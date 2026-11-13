<?php

namespace App\Domains\Aplicaciones\Features\BorrarRol;

use Illuminate\Http\JsonResponse;

/**
 * DELETE /api/iam/v1/aplicaciones/{nombre}/roles/{rol}. Only translates HTTP: 200 {borrado: true}; 409 EN_USO
 * with the impact (design D1 and D3 of aplicaciones-renombrar-y-borrar).
 */
final class BorrarRolEndpoint
{
    public function __invoke(string $nombre, string $rol, BorrarRolCommandHandler $borrar): JsonResponse
    {
        $borrar($nombre, $rol);

        return response()->json(['data' => ['borrado' => true]]);
    }
}
