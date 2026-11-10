<?php

namespace App\Domains\Usuarios\Features\QuitarRol;

use App\Domains\Usuarios\Domain\Dto\QuienActuaDto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UBO\Common\Login\Domain\UboUser;

/**
 * DELETE /api/iam/v1/usuarios/{uuid}/roles/{aplicacion}/{rol}. Only translates HTTP: 200 with whether
 * API-Auth closed the person's sessions.
 */
final class QuitarRolEndpoint
{
    public function __invoke(string $uuid, string $aplicacion, string $rol, QuitarRolCommandHandler $quitar): JsonResponse
    {
        /** @var UboUser $persona */
        $persona = Auth::guard('ubo')->user();
        $actor = new QuienActuaDto(Str::lower($persona->getAuthIdentifier()), $persona->scopes);

        return response()->json(['data' => ['sesiones_revocadas' => $quitar($actor, $uuid, $aplicacion, $rol)]]);
    }
}
