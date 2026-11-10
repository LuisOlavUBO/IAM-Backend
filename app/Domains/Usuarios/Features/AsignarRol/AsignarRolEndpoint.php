<?php

namespace App\Domains\Usuarios\Features\AsignarRol;

use App\Domains\Usuarios\Domain\Dto\QuienActuaDto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UBO\Common\Login\Domain\UboUser;

/**
 * POST /api/iam/v1/usuarios/{uuid}/roles. Only translates HTTP: 201 when the role is given, 200 when the
 * person already had it.
 */
final class AsignarRolEndpoint
{
    public function __invoke(string $uuid, AsignarRolRequest $request, AsignarRolCommandHandler $asignar): JsonResponse
    {
        /** @var UboUser $persona */
        $persona = Auth::guard('ubo')->user();
        $actor = new QuienActuaDto(Str::lower($persona->getAuthIdentifier()), $persona->scopes);

        $asignado = $asignar($actor, $uuid, (string) $request->validated('aplicacion'), (string) $request->validated('rol'));

        return response()->json(['data' => ['asignado' => $asignado]], $asignado ? 201 : 200);
    }
}
