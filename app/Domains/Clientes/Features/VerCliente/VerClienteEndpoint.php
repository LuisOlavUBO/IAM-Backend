<?php

namespace App\Domains\Clientes\Features\VerCliente;

/**
 * GET /api/iam/v1/clientes/{id}. Only translates HTTP.
 */
final class VerClienteEndpoint
{
    public function __invoke(string $id, VerClienteQueryHandler $ver): VerClienteResource
    {
        return new VerClienteResource($ver($id));
    }
}
