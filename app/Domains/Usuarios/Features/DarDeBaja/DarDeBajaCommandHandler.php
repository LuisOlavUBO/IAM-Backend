<?php

namespace App\Domains\Usuarios\Features\DarDeBaja;

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
 * Use case: disable a person and close their sessions in API-Auth (spec usuarios, design D2 to D5 of
 * usuarios-baja-y-clave). Their roles stay. The change and its audit entry are one transaction; the
 * revocation runs after the commit. A person already disabled changes nothing, but the rules run first so
 * an improper attempt is still audited.
 */
final class DarDeBajaCommandHandler
{
    private const ACCION = 'usuario.baja';

    public function __construct(
        private readonly IPersonasRepository $personas,
        private readonly IRolesDeUsuarioRepository $roles,
        private readonly IRevocadorDeSesiones $sesiones,
        private readonly IAuditor $auditoria,
    ) {}

    /** Whether API-Auth confirmed the revocation; null when the person was already disabled. */
    public function __invoke(QuienActuaDto $actor, string $uuid): ?bool
    {
        $uuid = Str::lower($uuid);
        $persona = Str::isUuid($uuid) ? $this->personas->estado($uuid) : null;
        if ($persona === null) {
            throw new UsuarioNoEncontradoException;
        }

        $this->verificar($uuid, function () use ($actor, $uuid, $persona) {
            ReglasDeRoles::verificarQueNoEsUnoMismo($actor, $uuid);
            $rolesDeIamAdmin = $this->personas->rolesDeIamAdmin($persona->id);
            ReglasDePersonas::verificarEscalamiento($actor, fn () => $rolesDeIamAdmin !== [], fn () => $this->roles->permisosDeAplicacion(ReglasDeRoles::IAM_ADMIN));
            ReglasDePersonas::verificarBajaDelUltimoAdministrador($rolesDeIamAdmin, $persona->activa, fn (int $rolId) => $this->roles->otrosActivosConRol($rolId, $persona->id));
        });

        if (! $persona->activa) {
            return null;
        }

        $cambio = DB::transaction(function () use ($persona, $uuid) {
            if (! $this->personas->darDeBaja($persona->id)) {
                return false;
            }
            $this->auditoria->registrar(self::ACCION, 'usuario', $uuid, ['activo' => true], ['activo' => false]);

            return true;
        });

        return $cambio ? $this->sesiones->revocar($uuid) : null;
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
