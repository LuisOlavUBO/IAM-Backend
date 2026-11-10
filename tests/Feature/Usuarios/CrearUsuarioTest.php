<?php

namespace Tests\Feature\Usuarios;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec usuarios: alta de una persona, clave temporal mostrada una sola vez, correo y RUT únicos y alta
 * auditada.
 */
class CrearUsuarioTest extends TestCase
{
    use IniciaSesionEnIam;
    use RefreshDatabase;

    private const MESA = '0199a1b2-c3d4-7e5f-8a9b-000000000002';

    private const PERMISOS_MESA = ['iam:aplicaciones.leer', 'iam:usuarios.administrar', 'iam:usuarios.leer'];

    private const ANA = ['nombre' => 'Ana Pérez', 'correo' => ' Ana.Perez@UBO.cl ', 'rut' => '12.345.678-5', 'acceso' => 'microsoft'];

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->insert(['uuid' => self::MESA, 'name' => 'Mesa Ayuda', 'email' => 'mesa@ubo.cl']);
    }

    private function comoMesa(): void
    {
        UboLogin::actingAs(id: self::MESA, roles: ['mesa-de-ayuda'], scopes: self::PERMISOS_MESA);
    }

    private function personas(): int
    {
        return DB::table('users')->count();
    }

    // --- Alta ---

    public function test_it_creates_a_microsoft_person_normalized_without_password_or_roles(): void
    {
        $this->comoMesa();

        $respuesta = $this->postJson('/api/iam/v1/usuarios', self::ANA)
            ->assertCreated()
            ->assertJsonPath('data.clave_temporal', null);

        $uuid = $respuesta->json('data.uuid');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid);
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));

        $persona = DB::table('users')->where('uuid', $uuid)->first();
        $this->assertSame('Ana Pérez', $persona->name);
        $this->assertSame('ana.perez@ubo.cl', $persona->email);
        $this->assertSame('12345678-5', $persona->rut);
        $this->assertNull($persona->password);
        $this->assertNull($persona->temporary_password_expires_at);
        $this->assertSame(0, DB::table('role_user')->where('user_id', $persona->id)->count());
    }

    public function test_the_rut_is_optional(): void
    {
        $this->comoMesa();

        $uuid = $this->postJson('/api/iam/v1/usuarios', ['nombre' => 'Sin Rut', 'correo' => 'sinrut@ubo.cl', 'rut' => '', 'acceso' => 'microsoft'])
            ->assertCreated()->json('data.uuid');

        $this->assertNull(DB::table('users')->where('uuid', $uuid)->value('rut'));
    }

    public function test_with_password_it_shows_the_temporary_password_once(): void
    {
        CarbonImmutable::setTestNow('2026-11-10 12:00:00');
        config(['iam.clave_temporal_horas' => 72]);
        $this->comoMesa();

        $respuesta = $this->postJson('/api/iam/v1/usuarios', [...self::ANA, 'acceso' => 'clave'])->assertCreated();

        $clave = $respuesta->json('data.clave_temporal.clave');
        $this->assertSame(14, strlen($clave));
        $this->assertMatchesRegularExpression('/^[a-zA-Z2-9]{4}-[a-zA-Z2-9]{4}-[a-zA-Z2-9]{4}$/', $clave);
        $this->assertTrue(CarbonImmutable::parse($respuesta->json('data.clave_temporal.vence'))->equalTo(CarbonImmutable::parse('2026-11-13 12:00:00')));
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));

        $uuid = $respuesta->json('data.uuid');
        $this->assertTrue(Hash::check($clave, DB::table('users')->where('uuid', $uuid)->value('password')));

        $detalle = $this->getJson("/api/iam/v1/usuarios/{$uuid}")->assertOk()
            ->assertJsonPath('data.tiene_clave', true);
        $this->assertNotNull($detalle->json('data.clave_temporal_vence'));
        $this->assertStringNotContainsString($clave, $detalle->getContent());
    }

    // --- Validación ---

    public function test_invalid_data_answers_422_by_field(): void
    {
        $this->comoMesa();
        $antes = $this->personas();

        $this->postJson('/api/iam/v1/usuarios', [...self::ANA, 'rut' => '12.345.678-9'])
            ->assertUnprocessable()->assertJsonPath('error', 'VALIDACION')->assertJsonStructure(['errores' => ['rut']]);
        $this->postJson('/api/iam/v1/usuarios', [...self::ANA, 'correo' => 'no-es-correo'])
            ->assertUnprocessable()->assertJsonStructure(['errores' => ['correo']]);
        $this->postJson('/api/iam/v1/usuarios', [...self::ANA, 'acceso' => 'google'])
            ->assertUnprocessable()->assertJsonStructure(['errores' => ['acceso']]);
        $this->postJson('/api/iam/v1/usuarios', [...self::ANA, 'nombre' => '   '])
            ->assertUnprocessable()->assertJsonStructure(['errores' => ['nombre']]);

        $this->assertSame($antes, $this->personas());
    }

    // --- Unicidad ---

    public function test_a_repeated_email_answers_409_regardless_of_case(): void
    {
        DB::table('users')->insert(['uuid' => '0199a1b2-c3d4-7e5f-8a9b-000000000009', 'name' => 'Ana', 'email' => 'ana.perez@ubo.cl']);
        $this->comoMesa();
        $antes = $this->personas();

        $this->postJson('/api/iam/v1/usuarios', [...self::ANA, 'correo' => 'ANA.PEREZ@ubo.cl'])
            ->assertConflict()->assertJsonPath('error', 'CORREO_EN_USO');

        $this->assertSame($antes, $this->personas());
    }

    public function test_a_repeated_rut_answers_409(): void
    {
        DB::table('users')->insert(['uuid' => '0199a1b2-c3d4-7e5f-8a9b-000000000009', 'name' => 'Otra', 'email' => 'otra@ubo.cl', 'rut' => '12345678-5']);
        $this->comoMesa();
        $antes = $this->personas();

        $this->postJson('/api/iam/v1/usuarios', self::ANA)
            ->assertConflict()->assertJsonPath('error', 'RUT_EN_USO');

        $this->assertSame($antes, $this->personas());
    }

    // --- Permiso y sesión ---

    public function test_read_only_people_get_403(): void
    {
        UboLogin::actingAs(id: self::MESA, roles: ['lector'], scopes: ['iam:usuarios.leer']);
        $antes = $this->personas();

        $this->postJson('/api/iam/v1/usuarios', self::ANA)
            ->assertForbidden()->assertJsonPath('error', 'ACCESO_DENEGADO');

        $this->assertSame($antes, $this->personas());
    }

    public function test_without_a_session_it_answers_401(): void
    {
        $this->postJson('/api/iam/v1/usuarios', self::ANA)
            ->assertUnauthorized()->assertJsonPath('error', 'NO_AUTENTICADO');
    }

    public function test_without_the_csrf_token_it_answers_419(): void
    {
        $this->setUpIam();
        $this->enforceCsrf();
        $this->comoMesa();
        $antes = $this->personas();

        $this->withBrowserSession()->postJson('/api/iam/v1/usuarios', self::ANA)->assertStatus(419);

        $this->assertSame($antes, $this->personas());
    }

    // --- Auditoría ---

    public function test_the_creation_is_audited_with_the_normalized_data(): void
    {
        $this->comoMesa();

        $uuid = $this->postJson('/api/iam/v1/usuarios', self::ANA)->assertCreated()->json('data.uuid');

        $entrada = DB::table('iam_auditoria')->where('accion', 'usuario.creacion')->sole();
        $this->assertSame('usuario', $entrada->entidad_tipo);
        $this->assertSame($uuid, $entrada->entidad_id);
        $this->assertSame(self::MESA, strtolower($entrada->actor_uuid));
        $this->assertNull($entrada->antes);
        $this->assertSame(
            ['nombre' => 'Ana Pérez', 'correo' => 'ana.perez@ubo.cl', 'rut' => '12345678-5', 'acceso' => 'microsoft'],
            json_decode($entrada->despues, true),
        );
    }

    public function test_the_audit_log_never_holds_the_password_or_its_hash(): void
    {
        $this->comoMesa();

        $respuesta = $this->postJson('/api/iam/v1/usuarios', [...self::ANA, 'acceso' => 'clave'])->assertCreated();
        $clave = $respuesta->json('data.clave_temporal.clave');
        $hash = DB::table('users')->where('uuid', $respuesta->json('data.uuid'))->value('password');

        $filas = json_encode(DB::table('iam_auditoria')->get()->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString($clave, $filas);
        $this->assertStringNotContainsString($hash, $filas);
        $this->assertSame('clave', json_decode(DB::table('iam_auditoria')->where('accion', 'usuario.creacion')->value('despues'), true)['acceso']);
    }
}
