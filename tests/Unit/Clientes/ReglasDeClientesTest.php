<?php

namespace Tests\Unit\Clientes;

use App\Domains\Clientes\Domain\ClienteDeIamAdminException;
use App\Domains\Clientes\Domain\ClienteSinRedireccionesException;
use App\Domains\Clientes\Domain\ClienteSinRolesException;
use App\Domains\Clientes\Domain\Dto\ClienteParaEscribirDto;
use App\Domains\Clientes\Domain\Dto\RolDeAplicacionDto;
use App\Domains\Clientes\Domain\NuevoCliente;
use App\Domains\Clientes\Domain\ReglasDeClientes;
use App\Domains\Clientes\Domain\RolDeIamAdminException;
use PHPUnit\Framework\TestCase;

/**
 * Design D3 and D4 of clientes-roles-y-redirecciones: the pure rules for a client's roles and redirections.
 */
class ReglasDeClientesTest extends TestCase
{
    private static function cliente(string $aplicacion = 'pago-honorarios', ?string $tipo = NuevoCliente::CLIENT_CREDENTIALS): ClienteParaEscribirDto
    {
        return new ClienteParaEscribirDto('0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a01', 'cliente', $aplicacion, true, false, $tipo, []);
    }

    public function test_a_client_of_iam_admin_is_refused(): void
    {
        ReglasDeClientes::verificarQueNoEsDeIamAdmin(self::cliente());

        $this->expectException(ClienteDeIamAdminException::class);
        ReglasDeClientes::verificarQueNoEsDeIamAdmin(self::cliente('iam-admin'));
    }

    public function test_only_service_clients_admit_roles(): void
    {
        ReglasDeClientes::verificarQueAdmiteRoles(self::cliente());

        foreach ([NuevoCliente::AUTHORIZATION_CODE, null] as $tipo) {
            try {
                ReglasDeClientes::verificarQueAdmiteRoles(self::cliente(tipo: $tipo));
                $this->fail('Se esperaba ClienteSinRolesException.');
            } catch (ClienteSinRolesException $rechazo) {
                $this->assertSame('CLIENTE_SIN_ROLES', $rechazo->code());
            }
        }
    }

    public function test_only_login_clients_admit_redirections(): void
    {
        ReglasDeClientes::verificarQueAdmiteRedirecciones(self::cliente(tipo: NuevoCliente::AUTHORIZATION_CODE));

        foreach ([NuevoCliente::CLIENT_CREDENTIALS, null] as $tipo) {
            try {
                ReglasDeClientes::verificarQueAdmiteRedirecciones(self::cliente(tipo: $tipo));
                $this->fail('Se esperaba ClienteSinRedireccionesException.');
            } catch (ClienteSinRedireccionesException $rechazo) {
                $this->assertSame('CLIENTE_SIN_REDIRECCIONES', $rechazo->code());
            }
        }
    }

    public function test_a_role_of_iam_admin_is_not_given_to_clients(): void
    {
        ReglasDeClientes::verificarRolAsignable(new RolDeAplicacionDto(1, 'api-maestros', 'maestros-lector'));

        $this->expectException(RolDeIamAdminException::class);
        ReglasDeClientes::verificarRolAsignable(new RolDeAplicacionDto(2, 'iam-admin', 'administrador'));
    }

    public function test_redirections_are_compared_regardless_of_order_and_repetitions(): void
    {
        $a = 'https://a.ubo.cl/cb';
        $b = 'https://b.ubo.cl/cb';

        $this->assertTrue(ReglasDeClientes::mismasRedirecciones([$a, $b], [$b, $a]));
        $this->assertTrue(ReglasDeClientes::mismasRedirecciones([$a], [$a, $a]));
        $this->assertFalse(ReglasDeClientes::mismasRedirecciones([$a], [$a, $b]));
        $this->assertFalse(ReglasDeClientes::mismasRedirecciones([$a, $b], [$a]));
    }
}
