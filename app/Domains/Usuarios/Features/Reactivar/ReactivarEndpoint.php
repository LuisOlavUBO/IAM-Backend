<?php

namespace App\Domains\Usuarios\Features\Reactivar;

use App\Domains\Usuarios\Domain\Dto\QuienActuaDto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UBO\Common\Login\Domain\UboUser;

/**
 * POST /api/iam/v1/usuarios/{uuid}/reactivacion. Only translates HTTP: 200 once the person is active.
 */
final class ReactivarEndpoint
{
    public function __invoke(string $uuid, ReactivarCommandHandler $reactivar): JsonResponse
    {
        /** @var UboUser $persona */
        $persona = Auth::guard('ubo')->user();
        $reactivar(new QuienActuaDto(Str::lower($persona->getAuthIdentifier()), $persona->scopes), $uuid);

        return response()->json(['data' => ['activo' => true]]);
    }
}
