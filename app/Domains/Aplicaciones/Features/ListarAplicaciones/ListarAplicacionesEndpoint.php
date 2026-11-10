<?php

namespace App\Domains\Aplicaciones\Features\ListarAplicaciones;

use App\Shared\Paginacion\RespuestaPaginada;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/iam/v1/aplicaciones. Only translates HTTP: validates, calls the use case and formats the page.
 */
final class ListarAplicacionesEndpoint
{
    public function __invoke(ListarAplicacionesRequest $request, ListarAplicacionesQueryHandler $listar): JsonResponse
    {
        return RespuestaPaginada::de($listar($request->filtro()), ListarAplicacionesResource::class);
    }
}
