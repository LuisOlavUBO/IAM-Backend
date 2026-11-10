<?php

namespace App\Domains\Usuarios\Domain;

use App\Domains\Usuarios\Domain\Dto\QuienActuaDto;
use App\Domains\Usuarios\Domain\Dto\RolDeAplicacionDto;

/**
 * The rules for giving and taking roles (spec usuarios, design D3). Pure: the data comes as arguments, so
 * they are tested without a database.
 */
final class ReglasDeRoles
{
    public const IAM_ADMIN = 'iam-admin';

    public const ADMINISTRADOR = 'administrador';

    /** Nobody changes their own roles nor revokes their own sessions. */
    public static function verificarQueNoEsUnoMismo(QuienActuaDto $actor, string $personaUuid): void
    {
        if ($actor->uuid === strtolower($personaUuid)) {
            throw new AccionSobreSiMismoException;
        }
    }

    /**
     * A role of iam-admin needs every permission of iam-admin; other roles only iam:usuarios.administrar,
     * which the route already checks. The permissions are asked only for a role of iam-admin.
     *
     * @param  callable(): list<string>  $permisosDeIamAdmin
     */
    public static function verificarEscalamiento(QuienActuaDto $actor, RolDeAplicacionDto $rol, callable $permisosDeIamAdmin): void
    {
        if ($rol->aplicacion !== self::IAM_ADMIN) {
            return;
        }

        $requeridos = $permisosDeIamAdmin();
        if ($requeridos === [] || array_diff($requeridos, $actor->permisos) !== []) {
            throw new EscalamientoDePrivilegiosException;
        }
    }

    /**
     * IAM keeps at least one active person with iam-admin/administrador. The data is asked only for that role.
     *
     * @param  callable(): bool  $personaActiva
     * @param  callable(): int  $otrosActivosConElRol  Active people with the role, besides this person
     */
    public static function verificarUltimoAdministrador(RolDeAplicacionDto $rol, callable $personaActiva, callable $otrosActivosConElRol): void
    {
        if ($rol->aplicacion !== self::IAM_ADMIN || $rol->nombre !== self::ADMINISTRADOR) {
            return;
        }

        if ($personaActiva() && $otrosActivosConElRol() === 0) {
            throw new UltimoAdministradorException;
        }
    }
}
