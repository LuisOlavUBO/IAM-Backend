<?php

namespace App\Domains\Aplicaciones\Features\DarDeBajaAplicacion;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/iam/v1/aplicaciones/{nombre}/baja. Only translates HTTP: 200 {activa: false}; 409
 * BAJA_SIN_CONFIRMAR with the impact without "confirmar_baja": true (design D2 of aplicaciones-baja).
 */
final class DarDeBajaAplicacionEndpoint
{
    public function __invoke(Request $request, string $nombre, DarDeBajaAplicacionCommandHandler $darDeBaja): JsonResponse
    {
        $darDeBaja($nombre, $request->boolean('confirmar_baja'));

        return response()->json(['data' => ['activa' => false]]);
    }
}
