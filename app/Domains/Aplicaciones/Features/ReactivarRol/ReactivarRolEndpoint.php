<?php

namespace App\Domains\Aplicaciones\Features\ReactivarRol;

use Illuminate\Http\JsonResponse;

/**
 * POST /api/iam/v1/aplicaciones/{nombre}/roles/{rol}/reactivacion. Only translates HTTP: 200 {activo: true}.
 */
final class ReactivarRolEndpoint
{
    public function __invoke(string $nombre, string $rol, ReactivarRolCommandHandler $reactivar): JsonResponse
    {
        $reactivar($nombre, $rol);

        return response()->json(['data' => ['activo' => true]]);
    }
}
