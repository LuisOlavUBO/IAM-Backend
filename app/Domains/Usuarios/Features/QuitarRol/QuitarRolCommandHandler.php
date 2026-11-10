<?php

namespace App\Domains\Usuarios\Features\QuitarRol;

use App\Domains\Usuarios\Domain\Dto\QuienActuaDto;
use App\Domains\Usuarios\Domain\Interfaces\IRevocadorDeSesiones;
use App\Domains\Usuarios\Domain\Interfaces\IRolesDeUsuarioRepository;
use App\Domains\Usuarios\Domain\ReglasDeRoles;
use App\Domains\Usuarios\Domain\RolNoAsignadoException;
use App\Domains\Usuarios\Domain\UsuarioNoEncontradoException;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: take a role from a person and close their sessions in API-Auth (spec usuarios, design D3, D5).
 * The removal and its audit entry are one transaction; the revocation runs after the commit, so a failure of
 * API-Auth never undoes the removal.
 */
final class QuitarRolCommandHandler
{
    private const ACCION = 'usuario.rol.retiro';

    public function __construct(
        private readonly IRolesDeUsuarioRepository $roles,
        private readonly IRevocadorDeSesiones $sesiones,
        private readonly IAuditor $auditoria,
    ) {}

    /** Whether API-Auth confirmed the revocation of the person's sessions. */
    public function __invoke(QuienActuaDto $actor, string $uuid, string $aplicacion, string $nombreRol): bool
    {
        $uuid = Str::lower($uuid);
        $personaId = Str::isUuid($uuid) ? $this->roles->idDePersona($uuid) : null;
        if ($personaId === null) {
            throw new UsuarioNoEncontradoException;
        }

        $this->verificar($uuid, fn () => ReglasDeRoles::verificarQueNoEsUnoMismo($actor, $uuid));

        $rol = $this->roles->rol($aplicacion, $nombreRol);
        if ($rol === null || ! $this->roles->tiene($personaId, $rol->id)) {
            throw new RolNoAsignadoException;
        }

        $this->verificar($uuid, function () use ($actor, $rol, $personaId) {
            ReglasDeRoles::verificarEscalamiento($actor, $rol, fn () => $this->roles->permisosDeAplicacion(ReglasDeRoles::IAM_ADMIN));
            ReglasDeRoles::verificarUltimoAdministrador(
                $rol,
                fn () => $this->roles->activa($personaId),
                fn () => $this->roles->otrosActivosConRol($rol->id, $personaId),
            );
        });

        DB::transaction(function () use ($personaId, $rol, $uuid) {
            $this->roles->quitar($personaId, $rol->id);
            $this->auditoria->registrar(self::ACCION, 'usuario', $uuid, ['rol' => $rol->nombreCompleto()], null);
        });

        return $this->sesiones->revocar($uuid);
    }

    /** Runs a rule; a refusal is audited before it reaches the portal. */
    private function verificar(string $uuid, callable $regla): void
    {
        try {
            $regla();
        } catch (DomainProblem $rechazo) {
            $this->auditoria->rechazar(self::ACCION, 'usuario', $uuid, $rechazo->code());

            throw $rechazo;
        }
    }
}
