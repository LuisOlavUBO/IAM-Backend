<?php

namespace App\Domains\Clientes\Features\QuitarRolACliente;

use Illuminate\Http\JsonResponse;

/**
 * DELETE /api/iam/v1/clientes/{id}/roles/{aplicacion}/{rol}. Only translates HTTP: 200 once the role is
 * removed (design D1).
 */
final class QuitarRolAClienteEndpoint
{
    public function __invoke(string $id, string $aplicacion, string $rol, QuitarRolAClienteCommandHandler $quitar): JsonResponse
    {
        $quitar($id, $aplicacion, $rol);

        return response()->json(['data' => ['quitado' => true]]);
    }
}
