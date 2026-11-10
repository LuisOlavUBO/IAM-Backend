<?php

namespace Tests\Feature\Usuarios;

use App\Domains\Usuarios\Domain\Interfaces\IRevocadorDeSesiones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec usuarios: asignar y quitar roles, revocar sesiones y sus protecciones.
 */
class AdministrarRolesTest extends TestCase
{
    use IniciaSesionEnIam;
    use RefreshDatabase;

    private const ADMIN = '0199a1b2-c3d4-7e5f-8a9b-000000000001';

    private const MESA = '0199a1b2-c3d4-7e5f-8a9b-000000000002';

    private const PEDRO = '0199a1b2-c3d4-7e5f-8a9b-000000000003';

    private const PERMISOS_IAM = [
        'iam:aplicaciones.administrar', 'iam:aplicaciones.leer', 'iam:auditoria.leer', 'iam:clientes.administrar',
        'iam:clientes.leer', 'iam:usuarios.administrar', 'iam:usuarios.leer',
    ];

    private const PERMISOS_MESA = ['iam:aplicaciones.leer', 'iam:usuarios.administrar', 'iam:usuarios.leer'];

    private RevocadorFalso $revocador;

    /** @var array<string, int> */
    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->revocador = new RevocadorFalso;
        $this->app->instance(IRevocadorDeSesiones::class, $this->revocador);

        $iamAdmin = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
        foreach (self::PERMISOS_IAM as $permiso) {
            DB::table('oauth_scopes')->insert(['application_id' => $iamAdmin, 'name' => $permiso, 'description' => $permiso]);
        }
        $honorarios = DB::table('applications')->insertGetId(['name' => 'pago-honorarios']);
        $this->roles['iam-admin/administrador'] = DB::table('roles')->insertGetId(['application_id' => $iamAdmin, 'name' => 'administrador']);
        $this->roles['iam-admin/mesa-de-ayuda'] = DB::table('roles')->insertGetId(['application_id' => $iamAdmin, 'name' => 'mesa-de-ayuda']);
        $this->roles['pago-honorarios/revisor'] = DB::table('roles')->insertGetId(['application_id' => $honorarios, 'name' => 'revisor']);

        foreach ([self::ADMIN => 'Ana Admin', self::MESA => 'Mesa Ayuda', self::PEDRO => 'Pedro Pérez'] as $uuid => $nombre) {
            DB::table('users')->insert(['uuid' => $uuid, 'name' => $nombre, 'email' => strtolower(strtok($nombre, ' ')).'@ubo.cl']);
        }
        $this->darRol(self::ADMIN, 'iam-admin/administrador');
    }

    private function idDe(string $uuid): int
    {
        return (int) DB::table('users')->where('uuid', $uuid)->value('id');
    }

    private function darRol(string $uuid, string $rol): void
    {
        DB::table('role_user')->insert(['user_id' => $this->idDe($uuid), 'role_id' => $this->roles[$rol]]);
    }

    private function tiene(string $uuid, string $rol): bool
    {
        return DB::table('role_user')->where('user_id', $this->idDe($uuid))->where('role_id', $this->roles[$rol])->exists();
    }

    private function comoAdmin(): void
    {
        UboLogin::actingAs(id: self::ADMIN, roles: ['administrador'], scopes: self::PERMISOS_IAM);
    }

    private function comoMesa(): void
    {
        UboLogin::actingAs(id: self::MESA, roles: ['mesa-de-ayuda'], scopes: self::PERMISOS_MESA);
    }

    /**
     * The audit entries, with the actor in lowercase (SQL Server returns uniqueidentifier in upper case) and
     * before/after decoded.
     *
     * @return list<array{accion: string, entidad_id: string, resultado: string, motivo: ?string, actor_uuid: ?string, antes: mixed, despues: mixed}>
     */
    private function auditoria(): array
    {
        return DB::table('iam_auditoria')->orderBy('id')->get(['accion', 'entidad_id', 'resultado', 'motivo', 'actor_uuid', 'antes', 'despues'])
            ->map(fn (object $fila) => [
                'accion' => $fila->accion,
                'entidad_id' => $fila->entidad_id,
                'resultado' => $fila->resultado,
                'motivo' => $fila->motivo,
                'actor_uuid' => $fila->actor_uuid === null ? null : strtolower($fila->actor_uuid),
                'antes' => $fila->antes === null ? null : json_decode($fila->antes, true),
                'despues' => $fila->despues === null ? null : json_decode($fila->despues, true),
            ])->all();
    }

    // --- Permiso ---

    public function test_read_only_people_get_403(): void
    {
        UboLogin::actingAs(id: self::MESA, roles: ['lector'], scopes: ['iam:usuarios.leer']);

        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/roles', ['aplicacion' => 'pago-honorarios', 'rol' => 'revisor'])
            ->assertForbidden()->assertJsonPath('error', 'ACCESO_DENEGADO');
        $this->deleteJson('/api/iam/v1/usuarios/'.self::ADMIN.'/roles/iam-admin/administrador')->assertForbidden();
        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/sesiones/revocar')->assertForbidden();

        $this->assertFalse($this->tiene(self::PEDRO, 'pago-honorarios/revisor'));
    }

    public function test_without_a_session_it_answers_401(): void
    {
        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/roles', ['aplicacion' => 'pago-honorarios', 'rol' => 'revisor'])
            ->assertUnauthorized()->assertJsonPath('error', 'NO_AUTENTICADO');
    }

    public function test_without_the_csrf_token_it_answers_419(): void
    {
        $this->setUpIam();
        $this->enforceCsrf();
        $this->comoMesa();

        $this->withBrowserSession()->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/roles', ['aplicacion' => 'pago-honorarios', 'rol' => 'revisor'])
            ->assertStatus(419);

        $this->assertFalse($this->tiene(self::PEDRO, 'pago-honorarios/revisor'));
    }

    public function test_an_unknown_person_answers_404(): void
    {
        $this->comoMesa();

        foreach (['0199a1b2-c3d4-7e5f-8a9b-0000000000ff', 'no-es-uuid'] as $uuid) {
            $this->postJson("/api/iam/v1/usuarios/{$uuid}/roles", ['aplicacion' => 'pago-honorarios', 'rol' => 'revisor'])
                ->assertNotFound()->assertJsonPath('error', 'USUARIO_NO_ENCONTRADO');
            $this->deleteJson("/api/iam/v1/usuarios/{$uuid}/roles/pago-honorarios/revisor")->assertNotFound();
            $this->postJson("/api/iam/v1/usuarios/{$uuid}/sesiones/revocar")->assertNotFound();
        }
        $this->assertSame([], $this->revocador->llamadas);
    }

    // --- Asignar ---

    public function test_the_help_desk_gives_a_role_and_it_is_audited(): void
    {
        $this->comoMesa();

        $this->postJson('/api/iam/v1/usuarios/'.strtoupper(self::PEDRO).'/roles', ['aplicacion' => 'pago-honorarios', 'rol' => 'revisor'])
            ->assertCreated()
            ->assertJsonPath('data.asignado', true);

        $this->assertTrue($this->tiene(self::PEDRO, 'pago-honorarios/revisor'));
        $this->assertSame([[
            'accion' => 'usuario.rol.asignacion', 'entidad_id' => self::PEDRO, 'resultado' => 'ok', 'motivo' => null,
            'actor_uuid' => self::MESA, 'antes' => null, 'despues' => ['rol' => 'pago-honorarios/revisor'],
        ]], $this->auditoria());
        // Giving a role does not close sessions.
        $this->assertSame([], $this->revocador->llamadas);
    }

    public function test_a_role_already_given_changes_nothing(): void
    {
        $this->darRol(self::PEDRO, 'pago-honorarios/revisor');
        $this->comoMesa();

        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/roles', ['aplicacion' => 'pago-honorarios', 'rol' => 'revisor'])
            ->assertOk()
            ->assertJsonPath('data.asignado', false);

        $this->assertSame(1, DB::table('role_user')->where('user_id', $this->idDe(self::PEDRO))->count());
        $this->assertSame([], $this->auditoria());
    }

    public function test_an_unknown_or_missing_role_answers_422(): void
    {
        $this->comoMesa();

        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/roles', ['aplicacion' => 'pago-honorarios', 'rol' => 'no-existe'])
            ->assertUnprocessable()->assertJsonPath('error', 'VALIDACION')->assertJsonStructure(['errores' => ['rol']]);
        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/roles', [])
            ->assertUnprocessable()->assertJsonStructure(['errores' => ['aplicacion', 'rol']]);

        $this->assertSame([], $this->auditoria());
    }

    // --- Quitar ---

    public function test_taking_a_role_closes_the_sessions_and_is_audited(): void
    {
        $this->darRol(self::PEDRO, 'pago-honorarios/revisor');
        $this->comoMesa();

        $this->deleteJson('/api/iam/v1/usuarios/'.self::PEDRO.'/roles/pago-honorarios/revisor')
            ->assertOk()
            ->assertJsonPath('data.sesiones_revocadas', true);

        $this->assertFalse($this->tiene(self::PEDRO, 'pago-honorarios/revisor'));
        $this->assertSame([self::PEDRO], $this->revocador->llamadas);
        $this->assertSame([[
            'accion' => 'usuario.rol.retiro', 'entidad_id' => self::PEDRO, 'resultado' => 'ok', 'motivo' => null,
            'actor_uuid' => self::MESA, 'antes' => ['rol' => 'pago-honorarios/revisor'], 'despues' => null,
        ]], $this->auditoria());
    }

    public function test_when_api_auth_fails_the_role_stays_taken(): void
    {
        $this->darRol(self::PEDRO, 'pago-honorarios/revisor');
        $this->revocador->confirma = false;
        $this->comoMesa();

        $this->deleteJson('/api/iam/v1/usuarios/'.self::PEDRO.'/roles/pago-honorarios/revisor')
            ->assertOk()
            ->assertJsonPath('data.sesiones_revocadas', false);

        $this->assertFalse($this->tiene(self::PEDRO, 'pago-honorarios/revisor'));
        $this->assertSame('usuario.rol.retiro', $this->auditoria()[0]['accion']);
    }

    public function test_a_role_the_person_does_not_have_answers_404(): void
    {
        $this->comoMesa();

        foreach (['pago-honorarios/revisor', 'pago-honorarios/no-existe', 'no-existe/revisor'] as $rol) {
            $this->deleteJson('/api/iam/v1/usuarios/'.self::PEDRO."/roles/{$rol}")
                ->assertNotFound()->assertJsonPath('error', 'ROL_NO_ASIGNADO');
        }
        $this->assertSame([], $this->revocador->llamadas);
        $this->assertSame([], $this->auditoria());
    }

    // --- Revocar sesiones ---

    public function test_retrying_the_revocation(): void
    {
        $this->comoMesa();

        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/sesiones/revocar')->assertNoContent();
        $this->assertSame([self::PEDRO], $this->revocador->llamadas);
        $this->assertSame('usuario.sesiones.revocacion', $this->auditoria()[0]['accion']);

        $this->revocador->confirma = false;
        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/sesiones/revocar')
            ->assertStatus(502)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'REVOCACION_FALLIDA');
        $this->assertCount(1, $this->auditoria());
    }

    // --- Protecciones ---

    public function test_the_help_desk_cannot_give_nor_take_roles_of_iam_admin(): void
    {
        $this->darRol(self::PEDRO, 'iam-admin/mesa-de-ayuda');
        $this->comoMesa();

        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/roles', ['aplicacion' => 'iam-admin', 'rol' => 'administrador'])
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'ESCALAMIENTO_DE_PRIVILEGIOS');
        $this->deleteJson('/api/iam/v1/usuarios/'.self::PEDRO.'/roles/iam-admin/mesa-de-ayuda')
            ->assertForbidden()->assertJsonPath('error', 'ESCALAMIENTO_DE_PRIVILEGIOS');

        $this->assertFalse($this->tiene(self::PEDRO, 'iam-admin/administrador'));
        $this->assertTrue($this->tiene(self::PEDRO, 'iam-admin/mesa-de-ayuda'));
        $this->assertSame([], $this->revocador->llamadas);
        $this->assertSame(
            [['usuario.rol.asignacion', 'rechazado', 'ESCALAMIENTO_DE_PRIVILEGIOS'], ['usuario.rol.retiro', 'rechazado', 'ESCALAMIENTO_DE_PRIVILEGIOS']],
            array_map(fn (array $e) => [$e['accion'], $e['resultado'], $e['motivo']], $this->auditoria()),
        );
    }

    public function test_an_administrator_gives_a_role_of_iam_admin(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/roles', ['aplicacion' => 'iam-admin', 'rol' => 'mesa-de-ayuda'])->assertCreated();

        $this->assertTrue($this->tiene(self::PEDRO, 'iam-admin/mesa-de-ayuda'));
    }

    public function test_nobody_changes_their_own_roles_nor_closes_their_own_sessions(): void
    {
        $this->darRol(self::MESA, 'pago-honorarios/revisor');
        $this->comoMesa();

        $this->postJson('/api/iam/v1/usuarios/'.self::MESA.'/roles', ['aplicacion' => 'pago-honorarios', 'rol' => 'revisor'])
            ->assertForbidden()->assertJsonPath('error', 'ACCION_SOBRE_SI_MISMO');
        $this->deleteJson('/api/iam/v1/usuarios/'.strtoupper(self::MESA).'/roles/pago-honorarios/revisor')
            ->assertForbidden()->assertJsonPath('error', 'ACCION_SOBRE_SI_MISMO');
        $this->postJson('/api/iam/v1/usuarios/'.self::MESA.'/sesiones/revocar')
            ->assertForbidden()->assertJsonPath('error', 'ACCION_SOBRE_SI_MISMO');

        $this->assertTrue($this->tiene(self::MESA, 'pago-honorarios/revisor'));
        $this->assertSame([], $this->revocador->llamadas);
        $this->assertSame(
            [['usuario.rol.asignacion', self::MESA, 'ACCION_SOBRE_SI_MISMO'], ['usuario.rol.retiro', self::MESA, 'ACCION_SOBRE_SI_MISMO'], ['usuario.sesiones.revocacion', self::MESA, 'ACCION_SOBRE_SI_MISMO']],
            array_map(fn (array $e) => [$e['accion'], $e['entidad_id'], $e['motivo']], $this->auditoria()),
        );
    }

    public function test_the_last_active_administrator_keeps_the_role(): void
    {
        // Pedro holds iam-admin/administrador; Ana acts with every permission but the other holder is disabled.
        DB::table('role_user')->where('role_id', $this->roles['iam-admin/administrador'])->delete();
        $this->darRol(self::PEDRO, 'iam-admin/administrador');
        $this->darRol(self::MESA, 'iam-admin/administrador');
        DB::table('users')->where('uuid', self::MESA)->update(['disabled_at' => now()]);
        $this->comoAdmin();

        $this->deleteJson('/api/iam/v1/usuarios/'.self::PEDRO.'/roles/iam-admin/administrador')
            ->assertStatus(409)->assertJsonPath('error', 'ULTIMO_ADMINISTRADOR');
        $this->assertTrue($this->tiene(self::PEDRO, 'iam-admin/administrador'));
        $this->assertSame('ULTIMO_ADMINISTRADOR', $this->auditoria()[0]['motivo']);

        // With another active administrator it can be taken; the disabled holder never blocks.
        $this->darRol(self::ADMIN, 'iam-admin/administrador');
        $this->deleteJson('/api/iam/v1/usuarios/'.self::PEDRO.'/roles/iam-admin/administrador')->assertOk();
        $this->deleteJson('/api/iam/v1/usuarios/'.self::MESA.'/roles/iam-admin/administrador')->assertOk();
    }
}

/**
 * A fake API-Auth revocation that records the people it closes.
 */
final class RevocadorFalso implements IRevocadorDeSesiones
{
    public bool $confirma = true;

    /** @var list<string> */
    public array $llamadas = [];

    public function revocar(string $uuid): bool
    {
        $this->llamadas[] = $uuid;

        return $this->confirma;
    }
}
