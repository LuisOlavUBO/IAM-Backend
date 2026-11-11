<?php

namespace App\Domains\Usuarios\Features\Reactivar;

use App\Domains\Usuarios\Domain\Dto\QuienActuaDto;
use App\Domains\Usuarios\Domain\Interfaces\IPersonasRepository;
use App\Domains\Usuarios\Domain\Interfaces\IRolesDeUsuarioRepository;
use App\Domains\Usuarios\Domain\ReglasDePersonas;
use App\Domains\Usuarios\Domain\ReglasDeRoles;
use App\Domains\Usuarios\Domain\UsuarioNoEncontradoException;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: reactivate a disabled person, with their roles (spec usuarios, design D2, D3 and D5 of
 * usuarios-baja-y-clave). Audited as usuario.alta, the name iam:grant-admin already uses. An active person
 * changes nothing; there are no sessions to close.
 */
final class ReactivarCommandHandler
{
    private const ACCION = 'usuario.alta';

    public function __construct(
        private readonly IPersonasRepository $personas,
        private readonly IRolesDeUsuarioRepository $roles,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(QuienActuaDto $actor, string $uuid): void
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

        if ($persona->activa) {
            return;
        }

        DB::transaction(function () use ($persona, $uuid) {
            if ($this->personas->reactivar($persona->id)) {
                $this->auditoria->registrar(self::ACCION, 'usuario', $uuid, ['activo' => false], ['activo' => true]);
            }
        });
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
