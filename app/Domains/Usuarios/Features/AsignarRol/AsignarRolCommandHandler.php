<?php

namespace App\Domains\Usuarios\Features\AsignarRol;

use App\Domains\Usuarios\Domain\Dto\QuienActuaDto;
use App\Domains\Usuarios\Domain\Interfaces\IRolesDeUsuarioRepository;
use App\Domains\Usuarios\Domain\ReglasDeRoles;
use App\Domains\Usuarios\Domain\UsuarioNoEncontradoException;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: give a role to a person (spec usuarios, design D3). Rule rejections are audited; the change and
 * its audit entry are saved in one transaction.
 */
final class AsignarRolCommandHandler
{
    private const ACCION = 'usuario.rol.asignacion';

    public function __construct(
        private readonly IRolesDeUsuarioRepository $roles,
        private readonly IAuditor $auditoria,
    ) {}

    /** True when the role was given; false when the person already had it (nothing changes). */
    public function __invoke(QuienActuaDto $actor, string $uuid, string $aplicacion, string $nombreRol): bool
    {
        $uuid = Str::lower($uuid);
        $personaId = Str::isUuid($uuid) ? $this->roles->idDePersona($uuid) : null;
        if ($personaId === null) {
            throw new UsuarioNoEncontradoException;
        }

        $this->verificar($uuid, fn () => ReglasDeRoles::verificarQueNoEsUnoMismo($actor, $uuid));

        $rol = $this->roles->rol($aplicacion, $nombreRol)
            ?? throw ValidationException::withMessages(['rol' => 'No existe ese rol en esa aplicación.']);

        $this->verificar($uuid, fn () => ReglasDeRoles::verificarEscalamiento($actor, $rol, fn () => $this->roles->permisosDeAplicacion(ReglasDeRoles::IAM_ADMIN)));

        if ($this->roles->tiene($personaId, $rol->id)) {
            return false;
        }

        DB::transaction(function () use ($personaId, $rol, $uuid) {
            $this->roles->asignar($personaId, $rol->id);
            $this->auditoria->registrar(self::ACCION, 'usuario', $uuid, null, ['rol' => $rol->nombreCompleto()]);
        });

        return true;
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
