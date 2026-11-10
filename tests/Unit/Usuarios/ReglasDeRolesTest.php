<?php

namespace Tests\Unit\Usuarios;

use App\Domains\Usuarios\Domain\AccionSobreSiMismoException;
use App\Domains\Usuarios\Domain\Dto\QuienActuaDto;
use App\Domains\Usuarios\Domain\Dto\RolDeAplicacionDto;
use App\Domains\Usuarios\Domain\EscalamientoDePrivilegiosException;
use App\Domains\Usuarios\Domain\ReglasDeRoles;
use App\Domains\Usuarios\Domain\UltimoAdministradorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec usuarios: the protections when giving and taking roles, without a database.
 */
class ReglasDeRolesTest extends TestCase
{
    private const ANA = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';

    private const PERMISOS_IAM = ['iam:auditoria.leer', 'iam:usuarios.administrar', 'iam:usuarios.leer'];

    private static function rol(string $aplicacion, string $nombre): RolDeAplicacionDto
    {
        return new RolDeAplicacionDto(1, $aplicacion, $nombre);
    }

    public function test_nobody_acts_on_themselves_whatever_the_case_of_the_uuid(): void
    {
        $this->expectException(AccionSobreSiMismoException::class);

        ReglasDeRoles::verificarQueNoEsUnoMismo(new QuienActuaDto(self::ANA, []), strtoupper(self::ANA));
    }

    public function test_acting_on_another_person_is_allowed(): void
    {
        ReglasDeRoles::verificarQueNoEsUnoMismo(new QuienActuaDto(self::ANA, []), '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5c');

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{list<string>, string, bool}>
     */
    public static function escalamientos(): array
    {
        return [
            'otro sistema con pocos permisos' => [['iam:usuarios.administrar'], 'pago-honorarios', false],
            'iam-admin sin todos los permisos' => [['iam:usuarios.administrar', 'iam:usuarios.leer'], 'iam-admin', true],
            'iam-admin con todos los permisos' => [self::PERMISOS_IAM, 'iam-admin', false],
            'iam-admin con permisos de más' => [[...self::PERMISOS_IAM, 'otro:permiso'], 'iam-admin', false],
        ];
    }

    /**
     * @param  list<string>  $permisosDelActor
     */
    #[DataProvider('escalamientos')]
    public function test_roles_of_iam_admin_need_every_permission_of_iam_admin(array $permisosDelActor, string $aplicacion, bool $rechaza): void
    {
        if ($rechaza) {
            $this->expectException(EscalamientoDePrivilegiosException::class);
        }

        ReglasDeRoles::verificarEscalamiento(new QuienActuaDto(self::ANA, $permisosDelActor), self::rol($aplicacion, 'administrador'), fn () => self::PERMISOS_IAM);

        $this->addToAssertionCount(1);
    }

    public function test_roles_of_other_applications_do_not_ask_for_data(): void
    {
        $noLlamar = fn () => $this->fail('La regla no debía consultar datos para un rol de otra aplicación.');
        $rol = self::rol('pago-honorarios', 'administrador');

        ReglasDeRoles::verificarEscalamiento(new QuienActuaDto(self::ANA, []), $rol, $noLlamar);
        ReglasDeRoles::verificarUltimoAdministrador($rol, $noLlamar, $noLlamar);

        $this->addToAssertionCount(1);
    }

    public function test_without_known_permissions_of_iam_admin_nobody_escalates(): void
    {
        $this->expectException(EscalamientoDePrivilegiosException::class);

        ReglasDeRoles::verificarEscalamiento(new QuienActuaDto(self::ANA, self::PERMISOS_IAM), self::rol('iam-admin', 'mesa-de-ayuda'), fn () => []);
    }

    /**
     * @return array<string, array{string, string, bool, int, bool}>
     */
    public static function ultimos(): array
    {
        return [
            'último administrador activo' => ['iam-admin', 'administrador', true, 0, true],
            'hay otro administrador activo' => ['iam-admin', 'administrador', true, 1, false],
            'la persona está de baja' => ['iam-admin', 'administrador', false, 0, false],
            'otro rol de iam-admin' => ['iam-admin', 'mesa-de-ayuda', true, 0, false],
            'administrador de otro sistema' => ['pago-honorarios', 'administrador', true, 0, false],
        ];
    }

    #[DataProvider('ultimos')]
    public function test_iam_keeps_an_active_administrator(string $aplicacion, string $nombre, bool $activa, int $otros, bool $rechaza): void
    {
        if ($rechaza) {
            $this->expectException(UltimoAdministradorException::class);
        }

        ReglasDeRoles::verificarUltimoAdministrador(self::rol($aplicacion, $nombre), fn () => $activa, fn () => $otros);

        $this->addToAssertionCount(1);
    }
}
