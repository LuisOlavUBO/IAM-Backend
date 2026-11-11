<?php

namespace App\Domains\Clientes\Features\CrearCliente;

use Illuminate\Http\JsonResponse;

/**
 * POST /api/iam/v1/clientes. Only translates HTTP: 201 with the id and the secret, marked no-store so
 * neither the browser nor a proxy keeps it (design D1 of clientes-crear).
 */
final class CrearClienteEndpoint
{
    public function __invoke(CrearClienteRequest $request, CrearClienteCommandHandler $crear): JsonResponse
    {
        return (new CrearClienteResource($crear($request->cliente())))
            ->response()
            ->setStatusCode(201)
            ->header('Cache-Control', 'no-store');
    }
}
