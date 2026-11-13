<?php

namespace App\Domains\Aplicaciones\Features\DarDeBajaRol;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/iam/v1/aplicaciones/{nombre}/roles/{rol}/baja. Only translates HTTP: 200 {activo: false}; 409
 * BAJA_SIN_CONFIRMAR with the impact without "confirmar_baja": true (design D2 of aplicaciones-baja).
 */
final class DarDeBajaRolEndpoint
{
    public function __invoke(Request $request, string $nombre, string $rol, DarDeBajaRolCommandHandler $darDeBaja): JsonResponse
    {
        $darDeBaja($nombre, $rol, $request->boolean('confirmar_baja'));

        return response()->json(['data' => ['activo' => false]]);
    }
}
