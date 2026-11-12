<?php

namespace Tests\Feature\Clientes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec clientes (clientes-roles-y-redirecciones): asignar y quitar roles a clientes de servicio, auditado,
 * solo en clientes client-credentials, sin roles de iam-admin y sin tocar los clientes de iam-admin.
 */
class RolesDeClientesTest extends TestCase
{
    use IniciaSesionEnIam;
    use RefreshDatabase;

    private const ADMIN = '0199a1b2-c3d4-7e5f-8a9b-000000000001';

    private const HONORARIOS = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a01';

    private const SATELITE = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a02';

    private const SISTEMA = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a03';

    /** @var array<string, int> */
    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $iamAdmin = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
        $apiAuth = DB::table('applications')->insertGetId(['name' => 'api-auth']);
        $honorarios = DB::table('applications')->insertGetId(['name' => 'pago-honorarios']);
        $maestros = DB::table('applications')->insertGetId(['name' => 'api-maestros']);

        foreach ([
            'iam-admin/administrador' => $iamAdmin,
            'api-auth/session-revoker' => $apiAuth,
            'api-maestros/maestros-lector' => $maestros,
            'pago-honorarios/backend' => $honorarios,
        ] as $nombre => $aplicacion) {
            $this->roles[$nombre] = DB::table('roles')->insertGetId(['application_id' => $aplicacion, 'name' => explode('/', $nombre)[1]]);
        }

        foreach ([
            [self::HONORARIOS, $honorarios, 'pago-honorarios-backend', '["client_credentials"]', '[]'],
            [self::SATELITE, $honorarios, 'satelite-web', '["authorization_code","refresh_token"]', '["https://satelite.ubo.cl/auth/callback"]'],
            [self::SISTEMA, $iamAdmin, 'iam-admin-sistema', '["client_credentials"]', '[]'],
        ] as [$id, $aplicacion, $nombre, $concesiones, $redirecciones]) {
            DB::table('oauth_clients')->insert([
                'id' => $id, 'application_id' => $aplicacion, 'name' => $nombre, 'secret' => 'hash',
                'redirect_uris' => $redirecciones, 'grant_types' => $concesiones, 'revoked' => false,
            ]);
        }
        $this->darRol(self::SISTEMA, 'api-auth/session-revoker');
    }

    private function darRol(string $cliente, string $rol): void
    {
        DB::table('oauth_client_role')->insert(['client_id' => $cliente, 'role_id' => $this->roles[$rol]]);
    }

    private function tiene(string $cliente, string $rol): bool
    {
        return DB::table('oauth_client_role')->where('client_id', $cliente)->where('role_id', $this->roles[$rol])->exists();
    }

    private function comoAdmin(): void
    {
        UboLogin::actingAs(id: self::ADMIN, roles: ['administrador'], scopes: ['iam:clientes.leer', 'iam:clientes.administrar']);
    }

    private function rechazoAuditado(string $accion, string $cliente, string $motivo): void
    {
        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame($accion, $entrada->accion);
        $this->assertSame('cliente', $entrada->entidad_tipo);
        $this->assertSame($cliente, $entrada->entidad_id);
        $this->assertSame('rechazado', $entrada->resultado);
        $this->assertSame($motivo, $entrada->motivo);
    }

    // --- Asignar ---

    public function test_a_role_of_another_application_is_given_and_audited(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/clientes/'.strtoupper(self::HONORARIOS).'/roles', ['aplicacion' => 'api-maestros', 'rol' => 'maestros-lector'])
            ->assertCreated()
            ->assertExactJson(['data' => ['asignado' => true]]);

        $this->getJson('/api/iam/v1/clientes/'.self::HONORARIOS)
            ->assertOk()
            ->assertJsonPath('data.roles.0.aplicacion', 'api-maestros')
            ->assertJsonPath('data.roles.0.nombre', 'maestros-lector');

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('cliente.rol.asignacion', $entrada->accion);
        $this->assertSame('cliente', $entrada->entidad_tipo);
        $this->assertSame(self::HONORARIOS, $entrada->entidad_id);
        $this->assertSame('ok', $entrada->resultado);
        $this->assertSame(self::ADMIN, strtolower((string) $entrada->actor_uuid));
        $this->assertNull($entrada->antes);
        $this->assertSame(['rol' => 'api-maestros/maestros-lector'], json_decode($entrada->despues, true));
    }

    public function test_a_role_the_client_already_has_answers_200_without_auditing(): void
    {
        $this->darRol(self::HONORARIOS, 'pago-honorarios/backend');
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/roles', ['aplicacion' => 'pago-honorarios', 'rol' => 'backend'])
            ->assertOk()
            ->assertExactJson(['data' => ['asignado' => false]]);

        $this->assertSame(1, DB::table('oauth_client_role')->where('client_id', self::HONORARIOS)->count());
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_an_unknown_role_answers_422(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/roles', ['aplicacion' => 'pago-honorarios', 'rol' => 'no-existe'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'VALIDACION')
            ->assertJsonPath('errores.rol.0', 'No existe ese rol en esa aplicación.');
        $this->postJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/roles', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['errores' => ['aplicacion', 'rol']]);

        $this->assertSame(0, DB::table('oauth_client_role')->where('client_id', self::HONORARIOS)->count());
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_a_role_of_iam_admin_is_refused_and_audited(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/roles', ['aplicacion' => 'iam-admin', 'rol' => 'administrador'])
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'ROL_DE_IAM_ADMIN');

        $this->assertFalse($this->tiene(self::HONORARIOS, 'iam-admin/administrador'));
        $this->rechazoAuditado('cliente.rol.asignacion', self::HONORARIOS, 'ROL_DE_IAM_ADMIN');
    }

    public function test_a_login_client_gets_no_roles_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/clientes/'.self::SATELITE.'/roles', ['aplicacion' => 'api-maestros', 'rol' => 'maestros-lector'])
            ->assertConflict()
            ->assertJsonPath('error', 'CLIENTE_SIN_ROLES');

        $this->assertFalse($this->tiene(self::SATELITE, 'api-maestros/maestros-lector'));
        $this->rechazoAuditado('cliente.rol.asignacion', self::SATELITE, 'CLIENTE_SIN_ROLES');
    }

    public function test_a_client_of_iam_admin_gets_no_roles_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/clientes/'.self::SISTEMA.'/roles', ['aplicacion' => 'api-maestros', 'rol' => 'maestros-lector'])
            ->assertConflict()
            ->assertJsonPath('error', 'CLIENTE_DE_IAM_ADMIN');

        $this->assertFalse($this->tiene(self::SISTEMA, 'api-maestros/maestros-lector'));
        $this->rechazoAuditado('cliente.rol.asignacion', self::SISTEMA, 'CLIENTE_DE_IAM_ADMIN');
    }

    // --- Quitar ---

    public function test_removing_a_role_takes_it_and_audits_it(): void
    {
        $this->darRol(self::HONORARIOS, 'api-maestros/maestros-lector');
        $this->comoAdmin();

        $this->deleteJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/roles/api-maestros/maestros-lector')
            ->assertOk()
            ->assertExactJson(['data' => ['quitado' => true]]);

        $this->getJson('/api/iam/v1/clientes/'.self::HONORARIOS)->assertOk()->assertJsonCount(0, 'data.roles');

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('cliente.rol.retiro', $entrada->accion);
        $this->assertSame(self::HONORARIOS, $entrada->entidad_id);
        $this->assertSame('ok', $entrada->resultado);
        $this->assertSame(['rol' => 'api-maestros/maestros-lector'], json_decode($entrada->antes, true));
        $this->assertNull($entrada->despues);
    }

    public function test_removing_a_role_the_client_does_not_have_answers_404(): void
    {
        $this->comoAdmin();

        foreach (['api-maestros/maestros-lector', 'api-maestros/no-existe'] as $rol) {
            $this->deleteJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/roles/'.$rol)
                ->assertNotFound()
                ->assertJsonPath('error', 'ROL_NO_ASIGNADO');
        }
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_removing_a_role_of_a_login_client_is_refused_and_audited(): void
    {
        $this->comoAdmin();

        $this->deleteJson('/api/iam/v1/clientes/'.self::SATELITE.'/roles/api-maestros/maestros-lector')
            ->assertConflict()
            ->assertJsonPath('error', 'CLIENTE_SIN_ROLES');

        $this->rechazoAuditado('cliente.rol.retiro', self::SATELITE, 'CLIENTE_SIN_ROLES');
    }

    public function test_a_client_of_iam_admin_keeps_its_role_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->deleteJson('/api/iam/v1/clientes/'.self::SISTEMA.'/roles/api-auth/session-revoker')
            ->assertConflict()
            ->assertJsonPath('error', 'CLIENTE_DE_IAM_ADMIN');

        $this->assertTrue($this->tiene(self::SISTEMA, 'api-auth/session-revoker'));
        $this->rechazoAuditado('cliente.rol.retiro', self::SISTEMA, 'CLIENTE_DE_IAM_ADMIN');
    }

    // --- Errores ---

    public function test_an_invalid_or_unknown_client_answers_404(): void
    {
        $this->comoAdmin();

        foreach (['no-es-uuid', '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4aff'] as $id) {
            $this->postJson("/api/iam/v1/clientes/{$id}/roles", ['aplicacion' => 'api-maestros', 'rol' => 'maestros-lector'])
                ->assertNotFound()
                ->assertJsonPath('error', 'CLIENTE_NO_ENCONTRADO');
            $this->deleteJson("/api/iam/v1/clientes/{$id}/roles/api-maestros/maestros-lector")
                ->assertNotFound()
                ->assertJsonPath('error', 'CLIENTE_NO_ENCONTRADO');
        }
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_the_help_desk_gets_403(): void
    {
        $this->darRol(self::HONORARIOS, 'api-maestros/maestros-lector');
        UboLogin::actingAs(id: self::ADMIN, roles: ['mesa-de-ayuda'], scopes: ['iam:clientes.leer', 'iam:usuarios.administrar']);

        $this->postJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/roles', ['aplicacion' => 'pago-honorarios', 'rol' => 'backend'])
            ->assertForbidden()
            ->assertJsonPath('error', 'ACCESO_DENEGADO');
        $this->deleteJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/roles/api-maestros/maestros-lector')
            ->assertForbidden()
            ->assertJsonPath('error', 'ACCESO_DENEGADO');

        $this->assertFalse($this->tiene(self::HONORARIOS, 'pago-honorarios/backend'));
        $this->assertTrue($this->tiene(self::HONORARIOS, 'api-maestros/maestros-lector'));
    }

    public function test_without_a_session_it_answers_401(): void
    {
        $this->postJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/roles', ['aplicacion' => 'pago-honorarios', 'rol' => 'backend'])
            ->assertUnauthorized()
            ->assertJsonPath('error', 'NO_AUTENTICADO');
        $this->deleteJson('/api/iam/v1/clientes/'.self::SISTEMA.'/roles/api-auth/session-revoker')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'NO_AUTENTICADO');

        $this->assertFalse($this->tiene(self::HONORARIOS, 'pago-honorarios/backend'));
        $this->assertTrue($this->tiene(self::SISTEMA, 'api-auth/session-revoker'));
    }

    public function test_without_the_csrf_token_it_answers_419(): void
    {
        $this->setUpIam();
        $this->enforceCsrf();
        $this->darRol(self::HONORARIOS, 'api-maestros/maestros-lector');
        $this->comoAdmin();

        $this->withBrowserSession()->postJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/roles', ['aplicacion' => 'pago-honorarios', 'rol' => 'backend'])->assertStatus(419);
        $this->withBrowserSession()->deleteJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/roles/api-maestros/maestros-lector')->assertStatus(419);

        $this->assertFalse($this->tiene(self::HONORARIOS, 'pago-honorarios/backend'));
        $this->assertTrue($this->tiene(self::HONORARIOS, 'api-maestros/maestros-lector'));
    }
}
