<?php

namespace App\Domains\Usuarios\Features\DarDeBaja;

use App\Domains\Usuarios\Domain\Dto\QuienActuaDto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UBO\Common\Login\Domain\UboUser;

/**
 * POST /api/iam/v1/usuarios/{uuid}/baja. Only translates HTTP: 200 with whether API-Auth closed the
 * person's sessions (null when the person was already disabled).
 */
final class DarDeBajaEndpoint
{
    public function __invoke(string $uuid, DarDeBajaCommandHandler $darDeBaja): JsonResponse
    {
        /** @var UboUser $persona */
        $persona = Auth::guard('ubo')->user();
        $actor = new QuienActuaDto(Str::lower($persona->getAuthIdentifier()), $persona->scopes);

        return response()->json(['data' => ['sesiones_revocadas' => $darDeBaja($actor, $uuid)]]);
    }
}
