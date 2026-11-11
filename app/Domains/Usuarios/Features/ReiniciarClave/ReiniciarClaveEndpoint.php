<?php

namespace App\Domains\Usuarios\Features\ReiniciarClave;

use App\Domains\Usuarios\Domain\Dto\QuienActuaDto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UBO\Common\Login\Domain\UboUser;

/**
 * POST /api/iam/v1/usuarios/{uuid}/clave. Only translates HTTP: 200 with the temporary password, marked
 * no-store so neither the browser nor a proxy keeps it (design D6).
 */
final class ReiniciarClaveEndpoint
{
    public function __invoke(string $uuid, ReiniciarClaveCommandHandler $reiniciar): JsonResponse
    {
        /** @var UboUser $persona */
        $persona = Auth::guard('ubo')->user();
        $actor = new QuienActuaDto(Str::lower($persona->getAuthIdentifier()), $persona->scopes);

        return (new ReiniciarClaveResource($reiniciar($actor, $uuid)))
            ->response()
            ->header('Cache-Control', 'no-store');
    }
}
