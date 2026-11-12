<?php

namespace App\Domains\Clientes\Features\CambiarRedirecciones;

use Illuminate\Http\JsonResponse;

/**
 * PUT /api/iam/v1/clientes/{id}/redirecciones. Only translates HTTP: 200 with the client's redirections,
 * whether they changed or not (design D1).
 */
final class CambiarRedireccionesEndpoint
{
    public function __invoke(string $id, CambiarRedireccionesRequest $request, CambiarRedireccionesCommandHandler $cambiar): JsonResponse
    {
        return response()->json(['data' => ['redirecciones' => $cambiar($id, $request->redirecciones())]]);
    }
}
