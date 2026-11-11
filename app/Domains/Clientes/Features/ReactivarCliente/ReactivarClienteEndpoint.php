<?php

namespace App\Domains\Clientes\Features\ReactivarCliente;

use Illuminate\Http\JsonResponse;

/**
 * POST /api/iam/v1/clientes/{id}/reactivacion. Only translates HTTP: 200 once the client is active (design
 * D1).
 */
final class ReactivarClienteEndpoint
{
    public function __invoke(string $id, ReactivarClienteCommandHandler $reactivar): JsonResponse
    {
        $reactivar($id);

        return response()->json(['data' => ['revocado' => false]]);
    }
}
