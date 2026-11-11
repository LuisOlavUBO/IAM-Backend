<?php

namespace App\Domains\Clientes\Features\RevocarCliente;

use Illuminate\Http\JsonResponse;

/**
 * POST /api/iam/v1/clientes/{id}/revocacion. Only translates HTTP: 200 once the client is revoked (design D1).
 */
final class RevocarClienteEndpoint
{
    public function __invoke(string $id, RevocarClienteCommandHandler $revocar): JsonResponse
    {
        $revocar($id);

        return response()->json(['data' => ['revocado' => true]]);
    }
}
