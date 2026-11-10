<?php

namespace Tests\Feature\Auditoria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec auditoria: consulta del registro desde el portal (permiso, listado, forma de cada entrada y filtros).
 */
class ListarAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private const ANA = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';

    private const LUIS = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5c';

    private const CLIENTE = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5d';

    private function conPermiso(): void
    {
        UboLogin::actingAs(id: self::LUIS, roles: ['administrador'], scopes: ['iam:auditoria.leer']);
    }

    /**
     * An entry as AuditorBaseDatos writes it.
     *
     * @param  array<string, mixed>  $columnas
     */
    private function entrada(string $ocurridoEn, string $accion = 'rol.creacion', array $columnas = []): void
    {
        DB::table('iam_auditoria')->insert($columnas + [
            'ocurrido_en' => $ocurridoEn,
            'actor_tipo' => 'comando',
            'actor_uuid' => null,
            'accion' => $accion,
            'entidad_tipo' => 'rol',
            'entidad_id' => '7',
            'resultado' => 'ok',
            'motivo' => null,
            'antes' => null,
            'despues' => null,
            'trace_id' => null,
        ]);
    }

    /**
     * @return list<string>
     */
    private function acciones(string $consulta = ''): array
    {
        return array_column($this->getJson("/api/iam/v1/auditoria{$consulta}")->assertOk()->json('data'), 'accion');
    }

    public function test_an_administrator_can_read_the_log(): void
    {
        $this->entrada('2026-11-09 10:00:00');
        $this->conPermiso();

        $this->getJson('/api/iam/v1/auditoria')
            ->assertOk()
            ->assertJsonPath('data.0.accion', 'rol.creacion');
    }

    public function test_a_person_without_the_permission_gets_403(): void
    {
        UboLogin::actingAs(id: self::LUIS, roles: ['mesa-de-ayuda'], scopes: ['iam:usuarios.leer']);

        $this->getJson('/api/iam/v1/auditoria')
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'ACCESO_DENEGADO');
    }

    public function test_without_a_session_it_answers_401(): void
    {
        $this->getJson('/api/iam/v1/auditoria')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'NO_AUTENTICADO');
    }

    public function test_newest_first_and_paginated(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->entrada(sprintf('2026-11-%02d 10:00:00', $i), sprintf('accion.%02d', $i));
        }
        $this->conPermiso();

        $respuesta = $this->getJson('/api/iam/v1/auditoria')->assertOk();

        $this->assertCount(20, $respuesta->json('data'));
        $this->assertSame('accion.25', $respuesta->json('data.0.accion'));
        $this->assertSame('accion.06', $respuesta->json('data.19.accion'));
        $respuesta->assertJsonPath('meta', ['total' => 25, 'pagina' => 1, 'por_pagina' => 20]);

        $this->assertSame(['accion.05', 'accion.04', 'accion.03', 'accion.02', 'accion.01'], $this->acciones('?pagina=2'));
    }

    public function test_an_entry_of_a_command(): void
    {
        $this->entrada('2026-11-09 15:46:03.1234567', 'usuario.rol.asignacion', [
            'entidad_tipo' => 'usuario', 'entidad_id' => self::ANA,
            'despues' => '{"rol":"iam-admin/administrador"}', 'trace_id' => 'e9c41bd0b410027a6c0bf678b9e566f7',
        ]);
        $this->conPermiso();

        $entrada = $this->getJson('/api/iam/v1/auditoria')->assertOk()->json('data.0');

        $this->assertStringStartsWith('2026-11-09T15:46:03', $entrada['ocurrido_en']);
        $this->assertSame(['tipo' => 'comando', 'uuid' => null, 'nombre' => null, 'correo' => null], $entrada['actor']);
        // Ana is not in users in this test: the entity keeps its id without a name.
        $this->assertSame(['tipo' => 'usuario', 'id' => self::ANA, 'nombre' => null], $entrada['entidad']);
        $this->assertSame('ok', $entrada['resultado']);
        $this->assertNull($entrada['motivo']);
        $this->assertNull($entrada['antes']);
        $this->assertSame(['rol' => 'iam-admin/administrador'], $entrada['despues']);
        $this->assertSame('e9c41bd0b410027a6c0bf678b9e566f7', $entrada['trace_id']);
    }

    public function test_an_entry_of_a_person_has_their_name_and_email(): void
    {
        DB::table('users')->insert(['uuid' => self::ANA, 'name' => 'Ana Pérez', 'email' => 'ana@ubo.cl']);
        $this->entrada('2026-11-09 10:00:00', columnas: ['actor_tipo' => 'persona', 'actor_uuid' => self::ANA, 'antes' => '{}', 'despues' => 'no es json']);
        $this->conPermiso();

        $respuesta = $this->getJson('/api/iam/v1/auditoria')
            ->assertOk()
            ->assertJsonPath('data.0.actor', ['tipo' => 'persona', 'uuid' => self::ANA, 'nombre' => 'Ana Pérez', 'correo' => 'ana@ubo.cl'])
            ->assertJsonPath('data.0.despues', null);

        // An empty object stays an object.
        $this->assertStringContainsString('"antes":{}', $respuesta->getContent());
    }

    public function test_an_actor_that_no_longer_exists_keeps_their_uuid(): void
    {
        $this->entrada('2026-11-09 10:00:00', columnas: ['actor_tipo' => 'persona', 'actor_uuid' => self::ANA]);
        $this->conPermiso();

        $this->getJson('/api/iam/v1/auditoria')
            ->assertOk()
            ->assertJsonPath('data.0.actor', ['tipo' => 'persona', 'uuid' => self::ANA, 'nombre' => null, 'correo' => null]);
    }

    public function test_date_range_includes_both_whole_days(): void
    {
        $this->entrada('2026-11-08 23:59:59', 'dia.8');
        $this->entrada('2026-11-09 00:00:00', 'dia.9.inicio');
        $this->entrada('2026-11-09 23:59:59.9999999', 'dia.9.fin');
        $this->entrada('2026-11-10 00:00:00', 'dia.10');
        $this->conPermiso();

        $this->assertSame(['dia.9.fin', 'dia.9.inicio'], $this->acciones('?desde=2026-11-09&hasta=2026-11-09'));
        $this->assertSame(['dia.10', 'dia.9.fin', 'dia.9.inicio'], $this->acciones('?desde=2026-11-09'));
        $this->assertSame(['dia.9.fin', 'dia.9.inicio', 'dia.8'], $this->acciones('?hasta=2026-11-09'));
    }

    public function test_action_by_prefix_with_wildcards_as_text(): void
    {
        $this->entrada('2026-11-09 10:00:00', 'cliente.creacion');
        $this->entrada('2026-11-09 11:00:00', 'cliente.secreto.rotacion');
        $this->entrada('2026-11-09 12:00:00', 'usuario.cliente.algo');
        $this->entrada('2026-11-09 13:00:00', 'clienteXcreacion');
        $this->conPermiso();

        $this->assertSame(['cliente.secreto.rotacion', 'cliente.creacion'], $this->acciones('?accion=cliente.'));
        $this->assertSame([], $this->acciones('?accion=%25'));
        $this->assertSame([], $this->acciones('?accion=cliente_'));
    }

    public function test_history_of_one_entity(): void
    {
        $this->entrada('2026-11-09 10:00:00', 'cliente.creacion', ['entidad_tipo' => 'cliente', 'entidad_id' => self::CLIENTE]);
        $this->entrada('2026-11-09 11:00:00', 'cliente.creacion', ['entidad_tipo' => 'cliente', 'entidad_id' => '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5e']);
        $this->entrada('2026-11-09 12:00:00', 'usuario.creacion', ['entidad_tipo' => 'usuario', 'entidad_id' => self::CLIENTE]);
        $this->conPermiso();

        $respuesta = $this->getJson('/api/iam/v1/auditoria?entidad_tipo=cliente&entidad_id='.self::CLIENTE)->assertOk();

        $respuesta->assertJsonCount(1, 'data')->assertJsonPath('data.0.entidad', ['tipo' => 'cliente', 'id' => self::CLIENTE, 'nombre' => null]);
    }

    public function test_each_entity_has_its_current_name(): void
    {
        $iamAdmin = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
        $mesa = DB::table('roles')->insertGetId(['application_id' => $iamAdmin, 'name' => 'mesa-de-ayuda']);
        $permiso = DB::table('oauth_scopes')->insertGetId(['application_id' => $iamAdmin, 'name' => 'iam:usuarios.leer', 'description' => 'Ver usuarios']);
        DB::table('users')->insert(['uuid' => self::ANA, 'name' => 'Ana Pérez', 'email' => 'ana@ubo.cl']);
        DB::table('oauth_clients')->insert([
            'id' => self::CLIENTE, 'application_id' => $iamAdmin, 'name' => 'iam-admin-sistema', 'secret' => 'hash',
            'redirect_uris' => '[]', 'grant_types' => '["client_credentials"]', 'revoked' => false,
        ]);

        $this->entrada('2026-11-09 10:00:00', 'aplicacion.creacion', ['entidad_tipo' => 'aplicacion', 'entidad_id' => (string) $iamAdmin]);
        $this->entrada('2026-11-09 10:01:00', 'rol.permiso.asignacion', ['entidad_tipo' => 'rol', 'entidad_id' => (string) $mesa]);
        $this->entrada('2026-11-09 10:02:00', 'permiso.creacion', ['entidad_tipo' => 'permiso', 'entidad_id' => (string) $permiso]);
        $this->entrada('2026-11-09 10:03:00', 'usuario.rol.asignacion', ['entidad_tipo' => 'usuario', 'entidad_id' => self::ANA]);
        $this->entrada('2026-11-09 10:04:00', 'cliente.creacion', ['entidad_tipo' => 'cliente', 'entidad_id' => strtoupper(self::CLIENTE)]);
        $this->entrada('2026-11-09 10:05:00', 'rol.creacion', ['entidad_tipo' => 'rol', 'entidad_id' => '999999']);
        $this->entrada('2026-11-09 10:06:00', 'rol.creacion', ['entidad_tipo' => 'rol', 'entidad_id' => 'no-numerico']);
        $this->entrada('2026-11-09 10:07:00', 'otra.cosa', ['entidad_tipo' => 'desconocido', 'entidad_id' => '1']);
        $this->conPermiso();

        $entidades = array_column($this->getJson('/api/iam/v1/auditoria')->assertOk()->json('data'), 'entidad');

        $this->assertSame([
            ['tipo' => 'desconocido', 'id' => '1', 'nombre' => null],
            ['tipo' => 'rol', 'id' => 'no-numerico', 'nombre' => null],
            ['tipo' => 'rol', 'id' => '999999', 'nombre' => null],
            ['tipo' => 'cliente', 'id' => strtoupper(self::CLIENTE), 'nombre' => 'iam-admin-sistema'],
            ['tipo' => 'usuario', 'id' => self::ANA, 'nombre' => 'Ana Pérez'],
            ['tipo' => 'permiso', 'id' => (string) $permiso, 'nombre' => 'iam:usuarios.leer'],
            ['tipo' => 'rol', 'id' => (string) $mesa, 'nombre' => 'iam-admin/mesa-de-ayuda'],
            ['tipo' => 'aplicacion', 'id' => (string) $iamAdmin, 'nombre' => 'iam-admin'],
        ], $entidades);
    }

    public function test_rejected_attempts_of_one_person(): void
    {
        $this->entrada('2026-11-09 10:00:00', 'usuario.rol.asignacion', ['actor_tipo' => 'persona', 'actor_uuid' => self::ANA, 'resultado' => 'rechazado', 'motivo' => 'ESCALAMIENTO_DE_PRIVILEGIOS']);
        $this->entrada('2026-11-09 11:00:00', 'usuario.rol.asignacion', ['actor_tipo' => 'persona', 'actor_uuid' => self::ANA]);
        $this->entrada('2026-11-09 12:00:00', 'usuario.rol.asignacion', ['actor_tipo' => 'persona', 'actor_uuid' => self::LUIS, 'resultado' => 'rechazado', 'motivo' => 'OTRO']);
        $this->conPermiso();

        $this->getJson('/api/iam/v1/auditoria?resultado=rechazado&actor='.strtoupper(self::ANA))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.motivo', 'ESCALAMIENTO_DE_PRIVILEGIOS');
    }

    public function test_invalid_filters_answer_422(): void
    {
        $this->conPermiso();

        $casos = [
            'hasta=2026-11-01&desde=2026-11-09' => 'hasta',
            'desde=09-11-2026' => 'desde',
            'actor=no-es-uuid' => 'actor',
            'resultado=otro' => 'resultado',
            'por_pagina=500' => 'por_pagina',
        ];
        foreach ($casos as $consulta => $campo) {
            $this->getJson("/api/iam/v1/auditoria?{$consulta}")
                ->assertUnprocessable()
                ->assertJsonPath('error', 'VALIDACION')
                ->assertJsonStructure(['errores' => [$campo]]);
        }
    }

    public function test_reading_the_log_leaves_no_trace(): void
    {
        $this->entrada('2026-11-09 10:00:00');
        $this->conPermiso();

        $this->getJson('/api/iam/v1/auditoria')->assertOk();
        $this->getJson('/api/iam/v1/auditoria?pagina=2')->assertOk();

        $this->assertSame(1, DB::table('iam_auditoria')->count());
    }
}
