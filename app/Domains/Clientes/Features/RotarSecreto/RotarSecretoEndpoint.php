<?php

namespace App\Domains\Clientes\Features\RotarSecreto;

use Illuminate\Http\JsonResponse;

/**
 * POST /api/iam/v1/clientes/{id}/secreto. Only translates HTTP: 200 with the new secret, marked no-store so
 * neither the browser nor a proxy keeps it (design D1).
 */
final class RotarSecretoEndpoint
{
    public function __invoke(string $id, RotarSecretoRequest $request, RotarSecretoCommandHandler $rotar): JsonResponse
    {
        return (new RotarSecretoResource($rotar($id, $request->confirmar())))
            ->response()
            ->header('Cache-Control', 'no-store');
    }
}
