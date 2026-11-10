<?php

namespace App\Domains\Usuarios\Features\VerUsuario;

/**
 * GET /api/iam/v1/usuarios/{uuid}. Only translates HTTP.
 */
final class VerUsuarioEndpoint
{
    public function __invoke(string $uuid, VerUsuarioQueryHandler $ver): VerUsuarioResource
    {
        return new VerUsuarioResource($ver($uuid));
    }
}
