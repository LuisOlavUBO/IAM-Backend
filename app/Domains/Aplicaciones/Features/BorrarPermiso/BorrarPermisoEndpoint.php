<?php

namespace App\Domains\Aplicaciones\Features\BorrarPermiso;

use Illuminate\Http\JsonResponse;

/**
 * DELETE /api/iam/v1/aplicaciones/{nombre}/permisos/{permiso}. Only translates HTTP: 200 {borrado: true}; 409
 * EN_USO with the impact (design D1 and D3 of aplicaciones-renombrar-y-borrar).
 */
final class BorrarPermisoEndpoint
{
    public function __invoke(string $nombre, string $permiso, BorrarPermisoCommandHandler $borrar): JsonResponse
    {
        $borrar($nombre, $permiso);

        return response()->json(['data' => ['borrado' => true]]);
    }
}
