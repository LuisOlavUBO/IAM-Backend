<?php

namespace App\Domains\Aplicaciones\Features\CrearRol;

use Illuminate\Http\JsonResponse;

/**
 * POST /api/iam/v1/aplicaciones/{nombre}/roles. Only translates HTTP: 201 with the new role (design D1 of
 * aplicaciones-catalogo).
 */
final class CrearRolEndpoint
{
    public function __invoke(string $nombre, CrearRolRequest $request, CrearRolCommandHandler $crear): JsonResponse
    {
        return (new CrearRolResource($crear($nombre, $request->nombre(), $request->descripcion())))
            ->response()
            ->setStatusCode(201);
    }
}
