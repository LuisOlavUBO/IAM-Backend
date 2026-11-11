<?php

namespace Tests\Feature\Usuarios;

use App\Domains\Usuarios\Domain\Interfaces\IRevocadorDeSesiones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec usuarios (usuarios-baja-y-clave): dar de baja, reactivar y reiniciar la clave de una persona, con sus
 * protecciones.
 */
class BajaYClaveTest extends TestCase
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

    private const CLAVE_ANTERIOR = 'clave-anterior';

    /** @var object{confirma: bool, llamadas: list<string>} */
    private object $revocador;

    /** @var array<string, int> */
    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->revocador = new class implements IRevocadorDeSesiones
        {
            public bool $confirma = true;

            /** @var list<string> */
            public array $llamadas = [];

            public function revocar(string $uuid): bool
            {
                $this->llamadas[] = $uuid;

                return $this->confirma;
            }
        };
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
            DB::table('users')->insert([
                'uuid' => $uuid, 'name' => $nombre, 'email' => strtolower(strtok($nombre, ' ')).'@ubo.cl',
                'password' => Hash::make(self::CLAVE_ANTERIOR),
            ]);
        }
        $this->darRol(self::ADMIN, 'iam-admin/administrador');
        $this->darRol(self::PEDRO, 'pago-honorarios/revisor');
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

    private function deBaja(string $uuid): bool
    {
        return DB::table('users')->where('uuid', $uuid)->whereNotNull('disabled_at')->exists();
    }

    private function darDeBajaEnLaBase(string $uuid): void
    {
        DB::table('users')->where('uuid', $uuid)->update(['disabled_at' => '2026-01-02 03:04:05']);
    }

    private function clave(string $uuid): ?string
    {
        return DB::table('users')->where('uuid', $uuid)->value('password');
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

    /**
     * @return list<array{string, string, ?string}>
     */
    private function resumenAuditoria(): array
    {
        return array_map(fn (array $e) => [$e['accion'], $e['resultado'], $e['motivo']], $this->auditoria());
    }

    // --- Permiso ---

    public function test_read_only_people_get_403(): void
    {
        UboLogin::actingAs(id: self::MESA, roles: ['lector'], scopes: ['iam:usuarios.leer']);

        foreach (['baja', 'reactivacion', 'clave'] as $accion) {
            $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO."/{$accion}")
                ->assertForbidden()->assertJsonPath('error', 'ACCESO_DENEGADO');
        }

        $this->assertFalse($this->deBaja(self::PEDRO));
        $this->assertTrue(Hash::check(self::CLAVE_ANTERIOR, $this->clave(self::PEDRO)));
    }

    public function test_without_the_csrf_token_it_answers_419(): void
    {
        $this->setUpIam();
        $this->enforceCsrf();
        $this->comoMesa();

        $this->withBrowserSession()->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/baja')->assertStatus(419);

        $this->assertFalse($this->deBaja(self::PEDRO));
    }

    public function test_an_unknown_person_answers_404(): void
    {
        $this->comoMesa();

        foreach (['0199a1b2-c3d4-7e5f-8a9b-0000000000ff', 'no-es-uuid'] as $uuid) {
            foreach (['baja', 'reactivacion', 'clave'] as $accion) {
                $this->postJson("/api/iam/v1/usuarios/{$uuid}/{$accion}")
                    ->assertNotFound()->assertJsonPath('error', 'USUARIO_NO_ENCONTRADO');
            }
        }
        $this->assertSame([], $this->revocador->llamadas);
        $this->assertSame([], $this->auditoria());
    }

    // --- Baja ---

    public function test_disabling_keeps_the_roles_closes_the_sessions_and_is_audited(): void
    {
        $this->comoMesa();

        $this->postJson('/api/iam/v1/usuarios/'.strtoupper(self::PEDRO).'/baja')
            ->assertOk()
            ->assertExactJson(['data' => ['sesiones_revocadas' => true]]);

        $this->assertTrue($this->deBaja(self::PEDRO));
        $this->assertTrue($this->tiene(self::PEDRO, 'pago-honorarios/revisor'));
        $this->assertSame([self::PEDRO], $this->revocador->llamadas);
        $this->assertSame([[
            'accion' => 'usuario.baja', 'entidad_id' => self::PEDRO, 'resultado' => 'ok', 'motivo' => null,
            'actor_uuid' => self::MESA, 'antes' => ['activo' => true], 'despues' => ['activo' => false],
        ]], $this->auditoria());
        $this->getJson('/api/iam/v1/usuarios/'.self::PEDRO)->assertJsonPath('data.estado', 'baja');
    }

    public function test_when_api_auth_fails_the_person_stays_disabled(): void
    {
        $this->revocador->confirma = false;
        $this->comoMesa();

        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/baja')
            ->assertOk()
            ->assertJsonPath('data.sesiones_revocadas', false);

        $this->assertTrue($this->deBaja(self::PEDRO));
        $this->assertSame([['usuario.baja', 'ok', null]], $this->resumenAuditoria());
    }

    public function test_a_person_already_disabled_changes_nothing(): void
    {
        $this->darDeBajaEnLaBase(self::PEDRO);
        $this->comoMesa();

        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/baja')
            ->assertOk()
            ->assertExactJson(['data' => ['sesiones_revocadas' => null]]);

        $this->assertSame('2026-01-02 03:04:05', substr((string) DB::table('users')->where('uuid', self::PEDRO)->value('disabled_at'), 0, 19));
        $this->assertSame([], $this->revocador->llamadas);
        $this->assertSame([], $this->auditoria());
    }

    // --- Reactivación ---

    public function test_reactivating_keeps_the_roles_and_is_audited_as_usuario_alta(): void
    {
        $this->darDeBajaEnLaBase(self::PEDRO);
        $this->comoMesa();

        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/reactivacion')
            ->assertOk()
            ->assertExactJson(['data' => ['activo' => true]]);

        $this->assertFalse($this->deBaja(self::PEDRO));
        $this->assertTrue($this->tiene(self::PEDRO, 'pago-honorarios/revisor'));
        $this->assertSame([[
            'accion' => 'usuario.alta', 'entidad_id' => self::PEDRO, 'resultado' => 'ok', 'motivo' => null,
            'actor_uuid' => self::MESA, 'antes' => ['activo' => false], 'despues' => ['activo' => true],
        ]], $this->auditoria());
        $this->assertSame([], $this->revocador->llamadas);
    }

    public function test_reactivating_an_active_person_changes_nothing(): void
    {
        $this->comoMesa();

        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/reactivacion')->assertOk();

        $this->assertFalse($this->deBaja(self::PEDRO));
        $this->assertSame([], $this->auditoria());
    }

    // --- Reinicio de clave ---

    public function test_resetting_the_password_gives_a_new_temporary_one_once(): void
    {
        $this->comoMesa();

        $respuesta = $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/clave')
            ->assertOk()
            ->assertJsonPath('data.sesiones_revocadas', true)
            ->assertJsonStructure(['data' => ['clave_temporal' => ['clave', 'vence'], 'sesiones_revocadas']]);

        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
        $clave = $respuesta->json('data.clave_temporal.clave');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}$/', $clave);
        $hash = $this->clave(self::PEDRO);
        $this->assertTrue(Hash::check($clave, $hash));
        $this->assertFalse(Hash::check(self::CLAVE_ANTERIOR, $hash));
        $this->assertSame([self::PEDRO], $this->revocador->llamadas);

        $this->assertSame([[
            'accion' => 'usuario.clave.reinicio', 'entidad_id' => self::PEDRO, 'resultado' => 'ok', 'motivo' => null,
            'actor_uuid' => self::MESA, 'antes' => null, 'despues' => null,
        ]], $this->auditoria());
        $filas = json_encode(DB::table('iam_auditoria')->get()->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString($clave, $filas);
        $this->assertStringNotContainsString($hash, $filas);

        $detalle = $this->getJson('/api/iam/v1/usuarios/'.self::PEDRO)->assertOk();
        $this->assertNotNull($detalle->json('data.clave_temporal_vence'));
        $this->assertStringNotContainsString($clave, (string) $detalle->getContent());
    }

    public function test_a_person_who_only_used_microsoft_gets_a_password(): void
    {
        DB::table('users')->where('uuid', self::PEDRO)->update(['password' => null, 'entra_oid' => '0199a1b2-c3d4-7e5f-8a9b-0000000000aa']);
        $this->revocador->confirma = false;
        $this->comoMesa();

        $respuesta = $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/clave')
            ->assertOk()
            ->assertJsonPath('data.sesiones_revocadas', false);

        $this->assertTrue(Hash::check($respuesta->json('data.clave_temporal.clave'), $this->clave(self::PEDRO)));
        $this->getJson('/api/iam/v1/usuarios/'.self::PEDRO)
            ->assertJsonPath('data.tiene_clave', true)
            ->assertJsonPath('data.microsoft_vinculado', true);
    }

    // --- Protecciones ---

    public function test_the_help_desk_cannot_act_on_a_person_of_iam_admin(): void
    {
        $this->darRol(self::PEDRO, 'iam-admin/mesa-de-ayuda');
        $this->comoMesa();

        foreach (['baja', 'clave', 'reactivacion'] as $accion) {
            $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO."/{$accion}")
                ->assertForbidden()
                ->assertHeader('Content-Type', 'application/problem+json')
                ->assertJsonPath('error', 'ESCALAMIENTO_DE_PRIVILEGIOS');
        }

        $this->assertFalse($this->deBaja(self::PEDRO));
        $this->assertTrue(Hash::check(self::CLAVE_ANTERIOR, $this->clave(self::PEDRO)));
        $this->assertSame([], $this->revocador->llamadas);
        $this->assertSame([
            ['usuario.baja', 'rechazado', 'ESCALAMIENTO_DE_PRIVILEGIOS'],
            ['usuario.clave.reinicio', 'rechazado', 'ESCALAMIENTO_DE_PRIVILEGIOS'],
            ['usuario.alta', 'rechazado', 'ESCALAMIENTO_DE_PRIVILEGIOS'],
        ], $this->resumenAuditoria());
    }

    public function test_an_administrator_acts_on_a_person_of_iam_admin(): void
    {
        $this->darRol(self::PEDRO, 'iam-admin/mesa-de-ayuda');
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/clave')->assertOk();
        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/baja')->assertOk()->assertJsonPath('data.sesiones_revocadas', true);
        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/reactivacion')->assertOk();

        $this->assertSame([
            ['usuario.clave.reinicio', 'ok', null], ['usuario.baja', 'ok', null], ['usuario.alta', 'ok', null],
        ], $this->resumenAuditoria());
    }

    public function test_nobody_acts_on_themselves(): void
    {
        $this->comoMesa();

        foreach (['baja', 'clave', 'reactivacion'] as $accion) {
            $this->postJson('/api/iam/v1/usuarios/'.strtoupper(self::MESA)."/{$accion}")
                ->assertForbidden()->assertJsonPath('error', 'ACCION_SOBRE_SI_MISMO');
        }

        $this->assertFalse($this->deBaja(self::MESA));
        $this->assertTrue(Hash::check(self::CLAVE_ANTERIOR, $this->clave(self::MESA)));
        $this->assertSame([], $this->revocador->llamadas);
        $this->assertSame([
            ['usuario.baja', 'rechazado', 'ACCION_SOBRE_SI_MISMO'],
            ['usuario.clave.reinicio', 'rechazado', 'ACCION_SOBRE_SI_MISMO'],
            ['usuario.alta', 'rechazado', 'ACCION_SOBRE_SI_MISMO'],
        ], $this->resumenAuditoria());
    }

    public function test_the_last_active_administrator_is_not_disabled(): void
    {
        // Pedro holds iam-admin/administrador; the other holder (Mesa) is disabled; Ana acts with every permission.
        DB::table('role_user')->where('role_id', $this->roles['iam-admin/administrador'])->delete();
        $this->darRol(self::PEDRO, 'iam-admin/administrador');
        $this->darRol(self::MESA, 'iam-admin/administrador');
        $this->darDeBajaEnLaBase(self::MESA);
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/baja')
            ->assertStatus(409)->assertJsonPath('error', 'ULTIMO_ADMINISTRADOR');
        $this->assertFalse($this->deBaja(self::PEDRO));
        $this->assertSame([], $this->revocador->llamadas);
        $this->assertSame([['usuario.baja', 'rechazado', 'ULTIMO_ADMINISTRADOR']], $this->resumenAuditoria());

        // Resetting the password of the last administrator is allowed; with another active one, so is disabling.
        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/clave')->assertOk();
        $this->darRol(self::ADMIN, 'iam-admin/administrador');
        $this->postJson('/api/iam/v1/usuarios/'.self::PEDRO.'/baja')->assertOk();
        $this->assertTrue($this->deBaja(self::PEDRO));
    }
}
