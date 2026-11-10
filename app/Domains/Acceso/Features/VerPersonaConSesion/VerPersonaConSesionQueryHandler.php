<?php

namespace App\Domains\Acceso\Features\VerPersonaConSesion;

use App\Domains\Usuarios\Contracts\IDirectorioUsuariosService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UBO\Common\Login\Domain\UboUser;

/**
 * Use case: who the person with a session is and what they may do (spec acceso-portal). Roles and
 * permissions come from the session's token; name and email from Usuarios, through its Contracts.
 */
final class VerPersonaConSesionQueryHandler
{
    public function __construct(private readonly IDirectorioUsuariosService $directorio) {}

    /**
     * @return array{uuid: string, nombre: ?string, correo: ?string, roles: list<string>, permisos: list<string>}
     */
    public function __invoke(): array
    {
        /** @var UboUser $persona */
        $persona = Auth::guard('ubo')->user();
        $uuid = Str::lower($persona->getAuthIdentifier());
        $datos = $this->directorio->persona($uuid);

        return [
            'uuid' => $uuid,
            'nombre' => $datos?->nombre,
            'correo' => $datos?->correo,
            'roles' => array_values($persona->roles),
            'permisos' => array_values($persona->scopes),
        ];
    }
}
