<?php

namespace App\Domains\Usuarios\Features\ListarUsuarios;

use App\Shared\Paginacion\RespuestaPaginada;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/iam/v1/usuarios. Only translates HTTP: validates, calls the use case and formats the page.
 */
final class ListarUsuariosEndpoint
{
    public function __invoke(ListarUsuariosRequest $request, ListarUsuariosQueryHandler $listar): JsonResponse
    {
        return RespuestaPaginada::de($listar($request->filtro()), ListarUsuariosResource::class);
    }
}
