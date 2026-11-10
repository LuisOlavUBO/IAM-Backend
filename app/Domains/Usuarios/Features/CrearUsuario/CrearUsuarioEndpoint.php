<?php

namespace App\Domains\Usuarios\Features\CrearUsuario;

use Illuminate\Http\JsonResponse;

/**
 * POST /api/iam/v1/usuarios. Only translates HTTP: 201 with the uuid and, with access by password, the
 * temporary password, marked no-store so neither the browser nor a proxy keeps it (design D1).
 */
final class CrearUsuarioEndpoint
{
    public function __invoke(CrearUsuarioRequest $request, CrearUsuarioCommandHandler $crear): JsonResponse
    {
        return (new CrearUsuarioResource($crear($request->persona())))
            ->response()
            ->setStatusCode(201)
            ->header('Cache-Control', 'no-store');
    }
}
