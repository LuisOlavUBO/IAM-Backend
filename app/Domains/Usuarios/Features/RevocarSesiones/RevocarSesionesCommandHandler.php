<?php

namespace App\Domains\Usuarios\Features\RevocarSesiones;

use App\Domains\Usuarios\Dto\QuienActuaDto;
use App\Domains\Usuarios\Exceptions\AccionSobreSiMismoException;
use App\Domains\Usuarios\Exceptions\RevocacionFallidaException;
use App\Domains\Usuarios\Exceptions\UsuarioNoEncontradoException;
use App\Domains\Usuarios\Interfaces\IRevocadorDeSesiones;
use App\Domains\Usuarios\Interfaces\IRolesDeUsuarioRepository;
use App\Domains\Usuarios\Rules\ReglasDeRoles;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Use case: close every session of a person in API-Auth, the retry after a removal whose revocation failed
 * (spec usuarios, design D6). Only a confirmed revocation is audited.
 */
final class RevocarSesionesCommandHandler
{
    private const ACCION = 'usuario.sesiones.revocacion';

    public function __construct(
        private readonly IRolesDeUsuarioRepository $roles,
        private readonly IRevocadorDeSesiones $sesiones,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(QuienActuaDto $actor, string $uuid): void
    {
        $uuid = Str::lower($uuid);
        if (! Str::isUuid($uuid) || $this->roles->idDePersona($uuid) === null) {
            throw new UsuarioNoEncontradoException;
        }

        try {
            ReglasDeRoles::verificarQueNoEsUnoMismo($actor, $uuid);
        } catch (AccionSobreSiMismoException $rechazo) {
            $this->auditoria->rechazar(self::ACCION, 'usuario', $uuid, $rechazo->code());

            throw $rechazo;
        }

        if (! $this->sesiones->revocar($uuid)) {
            throw new RevocacionFallidaException;
        }

        DB::transaction(fn () => $this->auditoria->registrar(self::ACCION, 'usuario', $uuid));
    }
}
