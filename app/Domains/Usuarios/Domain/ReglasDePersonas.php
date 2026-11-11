<?php

namespace App\Domains\Usuarios\Domain;

use App\Domains\Usuarios\Domain\Dto\QuienActuaDto;
use App\Domains\Usuarios\Domain\Dto\RolDeAplicacionDto;

/**
 * The rules for disabling, reactivating and resetting the password of a person (spec usuarios, design D3
 * and D4 of usuarios-baja-y-clave). Pure: the data comes as arguments, asked only when a rule needs it.
 * "Not on oneself" is ReglasDeRoles::verificarQueNoEsUnoMismo.
 */
final class ReglasDePersonas
{
    /**
     * Acting on a person with a role of iam-admin needs every permission of iam-admin: the help desk cannot
     * take over an administrator's account. The permissions are asked only for such a person.
     *
     * @param  callable(): bool  $tieneRolDeIamAdmin
     * @param  callable(): list<string>  $permisosDeIamAdmin
     */
    public static function verificarEscalamiento(QuienActuaDto $actor, callable $tieneRolDeIamAdmin, callable $permisosDeIamAdmin): void
    {
        if (! $tieneRolDeIamAdmin()) {
            return;
        }

        $requeridos = $permisosDeIamAdmin();
        if ($requeridos === [] || array_diff($requeridos, $actor->permisos) !== []) {
            throw new EscalamientoDePrivilegiosException('Para actuar sobre una persona con roles de iam-admin necesita todos los permisos de iam-admin.');
        }
    }

    /**
     * IAM keeps at least one active person with iam-admin/administrador: the last one is not disabled. The
     * other holders are counted only when the person is an active administrator.
     *
     * @param  list<RolDeAplicacionDto>  $rolesDeIamAdmin  The person's roles of iam-admin
     * @param  callable(int): int  $otrosActivosConElRol  Active people with the role (by id), besides this person
     */
    public static function verificarBajaDelUltimoAdministrador(array $rolesDeIamAdmin, bool $personaActiva, callable $otrosActivosConElRol): void
    {
        if (! $personaActiva) {
            return;
        }

        foreach ($rolesDeIamAdmin as $rol) {
            if ($rol->aplicacion === ReglasDeRoles::IAM_ADMIN && $rol->nombre === ReglasDeRoles::ADMINISTRADOR && $otrosActivosConElRol($rol->id) === 0) {
                throw new UltimoAdministradorException('Es la última persona activa con iam-admin/administrador: asigne el rol a otra persona antes de darla de baja.');
            }
        }
    }
}
