<?php

namespace Tests\Unit\Usuarios;

use App\Domains\Usuarios\Domain\Dto\QuienActuaDto;
use App\Domains\Usuarios\Domain\Dto\RolDeAplicacionDto;
use App\Domains\Usuarios\Domain\EscalamientoDePrivilegiosException;
use App\Domains\Usuarios\Domain\ReglasDePersonas;
use App\Domains\Usuarios\Domain\UltimoAdministradorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec usuarios: the protections when disabling, reactivating and resetting the password of a person,
 * without a database.
 */
class ReglasDePersonasTest extends TestCase
{
    private const ANA = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';

    private const PERMISOS_IAM = ['iam:auditoria.leer', 'iam:usuarios.administrar', 'iam:usuarios.leer'];

    /**
     * @return array<string, array{list<string>, bool, bool}>
     */
    public static function escalamientos(): array
    {
        return [
            'persona sin roles de iam-admin' => [['iam:usuarios.administrar'], false, false],
            'persona de iam-admin y actor sin todos los permisos' => [['iam:usuarios.administrar', 'iam:usuarios.leer'], true, true],
            'persona de iam-admin y actor con todos los permisos' => [self::PERMISOS_IAM, true, false],
            'persona de iam-admin y actor con permisos de más' => [[...self::PERMISOS_IAM, 'otro:permiso'], true, false],
        ];
    }

    /**
     * @param  list<string>  $permisosDelActor
     */
    #[DataProvider('escalamientos')]
    public function test_acting_on_a_person_of_iam_admin_needs_every_permission_of_iam_admin(array $permisosDelActor, bool $esDeIamAdmin, bool $rechaza): void
    {
        if ($rechaza) {
            $this->expectException(EscalamientoDePrivilegiosException::class);
        }

        ReglasDePersonas::verificarEscalamiento(new QuienActuaDto(self::ANA, $permisosDelActor), fn () => $esDeIamAdmin, fn () => self::PERMISOS_IAM);

        $this->addToAssertionCount(1);
    }

    public function test_a_person_without_roles_of_iam_admin_does_not_ask_for_the_permissions(): void
    {
        ReglasDePersonas::verificarEscalamiento(
            new QuienActuaDto(self::ANA, []),
            fn () => false,
            fn () => $this->fail('La regla no debía pedir los permisos de iam-admin.'),
        );

        $this->addToAssertionCount(1);
    }

    public function test_without_known_permissions_of_iam_admin_nobody_acts_on_its_people(): void
    {
        $this->expectException(EscalamientoDePrivilegiosException::class);

        ReglasDePersonas::verificarEscalamiento(new QuienActuaDto(self::ANA, self::PERMISOS_IAM), fn () => true, fn () => []);
    }

    /**
     * @return array<string, array{list<array{string, string}>, bool, int, bool}>
     */
    public static function ultimos(): array
    {
        return [
            'último administrador activo' => [[['iam-admin', 'mesa-de-ayuda'], ['iam-admin', 'administrador']], true, 0, true],
            'hay otro administrador activo' => [[['iam-admin', 'administrador']], true, 1, false],
            'la persona ya está de baja' => [[['iam-admin', 'administrador']], false, 0, false],
            'otro rol de iam-admin' => [[['iam-admin', 'mesa-de-ayuda']], true, 0, false],
            'sin roles de iam-admin' => [[], true, 0, false],
        ];
    }

    /**
     * @param  list<array{string, string}>  $roles
     */
    #[DataProvider('ultimos')]
    public function test_the_last_active_administrator_is_not_disabled(array $roles, bool $activa, int $otros, bool $rechaza): void
    {
        if ($rechaza) {
            $this->expectException(UltimoAdministradorException::class);
        }

        $roles = array_map(fn (array $rol) => new RolDeAplicacionDto(7, $rol[0], $rol[1]), $roles);
        ReglasDePersonas::verificarBajaDelUltimoAdministrador($roles, $activa, fn (int $rolId) => $rolId === 7 ? $otros : $this->fail('Rol inesperado.'));

        $this->addToAssertionCount(1);
    }

    public function test_the_other_holders_are_counted_only_for_an_active_administrator(): void
    {
        $noLlamar = fn () => $this->fail('La regla no debía contar a otros administradores.');

        ReglasDePersonas::verificarBajaDelUltimoAdministrador([new RolDeAplicacionDto(7, 'iam-admin', 'administrador')], false, $noLlamar);
        ReglasDePersonas::verificarBajaDelUltimoAdministrador([new RolDeAplicacionDto(8, 'iam-admin', 'mesa-de-ayuda')], true, $noLlamar);

        $this->addToAssertionCount(1);
    }
}
