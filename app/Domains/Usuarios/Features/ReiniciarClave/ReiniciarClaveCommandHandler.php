<?php

namespace App\Domains\Usuarios\Features\ReiniciarClave;

use App\Domains\Usuarios\Domain\ClaveTemporal;
use App\Domains\Usuarios\Domain\Dto\QuienActuaDto;
use App\Domains\Usuarios\Domain\Interfaces\IPersonasRepository;
use App\Domains\Usuarios\Domain\Interfaces\IRevocadorDeSesiones;
use App\Domains\Usuarios\Domain\Interfaces\IRolesDeUsuarioRepository;
use App\Domains\Usuarios\Domain\ReglasDePersonas;
use App\Domains\Usuarios\Domain\ReglasDeRoles;
use App\Domains\Usuarios\Domain\UsuarioNoEncontradoException;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: give a person a new temporary password and close their sessions (spec usuarios, design D2, D3
 * and D6 of usuarios-baja-y-clave). Only its hash and expiry are saved, with an audit entry without values,
 * in one transaction; the revocation runs after the commit. The password lives only in memory and in the
 * response. A person who only signed in with Microsoft gets a password too.
 */
final class ReiniciarClaveCommandHandler
{
    private const ACCION = 'usuario.clave.reinicio';

    public function __construct(
        private readonly IPersonasRepository $personas,
        private readonly IRolesDeUsuarioRepository $roles,
        private readonly IRevocadorDeSesiones $sesiones,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(QuienActuaDto $actor, string $uuid): ClaveReiniciadaDto
    {
        $uuid = Str::lower($uuid);
        $persona = Str::isUuid($uuid) ? $this->personas->estado($uuid) : null;
        if ($persona === null) {
            throw new UsuarioNoEncontradoException;
        }

        $this->verificar($uuid, function () use ($actor, $uuid, $persona) {
            ReglasDeRoles::verificarQueNoEsUnoMismo($actor, $uuid);
            ReglasDePersonas::verificarEscalamiento(
                $actor,
                fn () => $this->personas->rolesDeIamAdmin($persona->id) !== [],
                fn () => $this->roles->permisosDeAplicacion(ReglasDeRoles::IAM_ADMIN),
            );
        });

        $clave = ClaveTemporal::generar();

        DB::transaction(function () use ($persona, $uuid, $clave) {
            $this->personas->cambiarClave($persona->id, $clave);
            $this->auditoria->registrar(self::ACCION, 'usuario', $uuid);
        });

        return new ClaveReiniciadaDto($clave, $this->sesiones->revocar($uuid));
    }

    /** Runs the rules; a refusal is audited before it reaches the portal. */
    private function verificar(string $uuid, callable $reglas): void
    {
        try {
            $reglas();
        } catch (DomainProblem $rechazo) {
            $this->auditoria->rechazar(self::ACCION, 'usuario', $uuid, $rechazo->code());

            throw $rechazo;
        }
    }
}
