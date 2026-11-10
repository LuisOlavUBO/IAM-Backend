<?php

namespace App\Domains\Auditoria\Features\ListarAuditoria;

use App\Shared\Paginacion\RespuestaPaginada;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/iam/v1/auditoria. Only translates HTTP: validates, calls the use case and formats the page.
 */
final class ListarAuditoriaEndpoint
{
    public function __invoke(ListarAuditoriaRequest $request, ListarAuditoriaQueryHandler $listar): JsonResponse
    {
        return RespuestaPaginada::de($listar($request->filtro()), ListarAuditoriaResource::class);
    }
}
