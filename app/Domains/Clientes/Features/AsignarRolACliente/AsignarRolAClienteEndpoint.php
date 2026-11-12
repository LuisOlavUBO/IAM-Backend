<?php

namespace App\Domains\Clientes\Features\AsignarRolACliente;

use Illuminate\Http\JsonResponse;

/**
 * POST /api/iam/v1/clientes/{id}/roles. Only translates HTTP: 201 when the role is given, 200 when the client
 * already had it (design D1).
 */
final class AsignarRolAClienteEndpoint
{
    public function __invoke(string $id, AsignarRolAClienteRequest $request, AsignarRolAClienteCommandHandler $asignar): JsonResponse
    {
        $asignado = $asignar($id, (string) $request->validated('aplicacion'), (string) $request->validated('rol'));

        return response()->json(['data' => ['asignado' => $asignado]], $asignado ? 201 : 200);
    }
}
