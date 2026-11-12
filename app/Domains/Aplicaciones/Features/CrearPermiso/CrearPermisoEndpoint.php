<?php

namespace App\Domains\Aplicaciones\Features\CrearPermiso;

use Illuminate\Http\JsonResponse;

/**
 * POST /api/iam/v1/aplicaciones/{nombre}/permisos. Only translates HTTP: 201 with the new permission (design
 * D1 of aplicaciones-catalogo).
 */
final class CrearPermisoEndpoint
{
    public function __invoke(string $nombre, CrearPermisoRequest $request, CrearPermisoCommandHandler $crear): JsonResponse
    {
        return (new CrearPermisoResource($crear($nombre, $request->nombre(), $request->descripcion())))
            ->response()
            ->setStatusCode(201);
    }
}
