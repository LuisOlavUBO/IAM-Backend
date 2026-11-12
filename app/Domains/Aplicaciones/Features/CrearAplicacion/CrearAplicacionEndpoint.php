<?php

namespace App\Domains\Aplicaciones\Features\CrearAplicacion;

use Illuminate\Http\JsonResponse;

/**
 * POST /api/iam/v1/aplicaciones. Only translates HTTP: 201 with the new application (design D1 of
 * aplicaciones-catalogo).
 */
final class CrearAplicacionEndpoint
{
    public function __invoke(CrearAplicacionRequest $request, CrearAplicacionCommandHandler $crear): JsonResponse
    {
        return (new CrearAplicacionResource($crear($request->nombre(), $request->descripcion())))
            ->response()
            ->setStatusCode(201);
    }
}
