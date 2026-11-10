<?php

namespace App\Domains\Usuarios\Features\RevocarSesiones;

use App\Domains\Usuarios\Domain\Dto\QuienActuaDto;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UBO\Common\Login\Domain\UboUser;

/**
 * POST /api/iam/v1/usuarios/{uuid}/sesiones/revocar. Only translates HTTP: 204 when API-Auth confirmed it.
 */
final class RevocarSesionesEndpoint
{
    public function __invoke(string $uuid, RevocarSesionesCommandHandler $revocar): Response
    {
        /** @var UboUser $persona */
        $persona = Auth::guard('ubo')->user();
        $revocar(new QuienActuaDto(Str::lower($persona->getAuthIdentifier()), $persona->scopes), $uuid);

        return response()->noContent();
    }
}
