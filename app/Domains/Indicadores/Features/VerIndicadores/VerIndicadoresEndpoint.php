<?php

namespace App\Domains\Indicadores\Features\VerIndicadores;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * GET /api/iam/v1/indicadores, only with the portal's session (no permission of its own). Only
 * translates HTTP: hands the use case the permissions of who asks among those that show a block.
 */
final class VerIndicadoresEndpoint
{
    public function __invoke(VerIndicadoresQueryHandler $ver): JsonResponse
    {
        $permisos = array_values(array_filter(
            array_unique(VerIndicadoresQueryHandler::BLOQUES),
            fn (string $permiso) => Gate::allows($permiso),
        ));

        return (new VerIndicadoresResource($ver($permisos)))->response();
    }
}
