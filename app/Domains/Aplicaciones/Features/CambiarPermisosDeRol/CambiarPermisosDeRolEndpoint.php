<?php

namespace App\Domains\Aplicaciones\Features\CambiarPermisosDeRol;

use Illuminate\Http\JsonResponse;

/**
 * PUT /api/iam/v1/aplicaciones/{nombre}/roles/{rol}/permisos. Only translates HTTP: 200 with the permissions
 * the role grants, whether they changed or not (design D1 of aplicaciones-catalogo).
 */
final class CambiarPermisosDeRolEndpoint
{
    public function __invoke(string $nombre, string $rol, CambiarPermisosDeRolRequest $request, CambiarPermisosDeRolCommandHandler $cambiar): JsonResponse
    {
        return response()->json(['data' => ['permisos' => $cambiar($nombre, $rol, $request->permisos())]]);
    }
}
