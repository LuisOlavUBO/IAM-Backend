<?php

namespace App\Domains\Aplicaciones\Features\VerAplicacion;

/**
 * GET /api/iam/v1/aplicaciones/{nombre}. Only translates HTTP.
 */
final class VerAplicacionEndpoint
{
    public function __invoke(string $nombre, VerAplicacionQueryHandler $ver): VerAplicacionResource
    {
        return new VerAplicacionResource($ver($nombre));
    }
}
