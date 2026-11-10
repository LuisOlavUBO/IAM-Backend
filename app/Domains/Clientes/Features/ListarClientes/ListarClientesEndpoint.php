<?php

namespace App\Domains\Clientes\Features\ListarClientes;

use App\Shared\Paginacion\RespuestaPaginada;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/iam/v1/clientes. Only translates HTTP: validates, calls the use case and formats the page.
 */
final class ListarClientesEndpoint
{
    public function __invoke(ListarClientesRequest $request, ListarClientesQueryHandler $listar): JsonResponse
    {
        return RespuestaPaginada::de($listar($request->filtro()), ListarClientesResource::class);
    }
}
