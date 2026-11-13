<?php

namespace Tests\Feature\Aplicaciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec aplicaciones: detalle de una aplicación, y sin identificadores internos ni escritura.
 */
class VerAplicacionTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISOS = [
        'iam:aplicaciones.administrar', 'iam:aplicaciones.leer', 'iam:auditoria.leer', 'iam:clientes.administrar',
        'iam:clientes.leer', 'iam:usuarios.administrar', 'iam:usuarios.leer',
    ];

    private const MESA_DE_AYUDA = ['iam:aplicaciones.leer', 'iam:usuarios.administrar', 'iam:usuarios.leer'];

    private function conPermiso(): void
    {
        UboLogin::actingAs(id: 'a', roles: ['mesa-de-ayuda'], scopes: ['iam:aplicaciones.leer']);
    }

    /**
     * iam-admin as iam:install leaves it, inserted in an order different from the expected one.
     */
    private function iamAdmin(): void
    {
        $aplicacion = DB::table('applications')->insertGetId([
            'name' => 'iam-admin', 'description' => 'Portal de administración de IAM',
            'created_at' => '2026-11-09 10:00:00', 'updated_at' => '2026-11-10 12:30:00',
        ]);

        $ids = [];
        foreach (array_reverse(self::PERMISOS) as $permiso) {
            $ids[$permiso] = DB::table('oauth_scopes')->insertGetId(['application_id' => $aplicacion, 'name' => $permiso, 'description' => "Descripción de {$permiso}"]);
        }

        $mesa = DB::table('roles')->insertGetId(['application_id' => $aplicacion, 'name' => 'mesa-de-ayuda', 'description' => 'Atiende a personas']);
        $administrador = DB::table('roles')->insertGetId(['application_id' => $aplicacion, 'name' => 'administrador', 'description' => 'Administra IAM']);

        foreach (self::PERMISOS as $permiso) {
            DB::table('oauth_scope_role')->insert(['role_id' => $administrador, 'oauth_scope_id' => $ids[$permiso]]);
        }
        foreach (self::MESA_DE_AYUDA as $permiso) {
            DB::table('oauth_scope_role')->insert(['role_id' => $mesa, 'oauth_scope_id' => $ids[$permiso]]);
        }
    }

    public function test_an_application_with_its_permissions_and_roles(): void
    {
        $this->iamAdmin();
        $this->conPermiso();

        $respuesta = $this->getJson('/api/iam/v1/aplicaciones/iam-admin')
            ->assertOk()
            ->assertJsonPath('data.nombre', 'iam-admin')
            ->assertJsonPath('data.descripcion', 'Portal de administración de IAM')
            ->assertJsonPath('data.permisos.0', ['nombre' => 'iam:aplicaciones.administrar', 'descripcion' => 'Descripción de iam:aplicaciones.administrar'])
            ->assertJsonPath('data.roles', [
                ['nombre' => 'administrador', 'descripcion' => 'Administra IAM', 'permisos' => self::PERMISOS, 'baja_desde' => null],
                ['nombre' => 'mesa-de-ayuda', 'descripcion' => 'Atiende a personas', 'permisos' => self::MESA_DE_AYUDA, 'baja_desde' => null],
            ]);

        $this->assertSame(self::PERMISOS, array_column($respuesta->json('data.permisos'), 'nombre'));
        $this->assertStringStartsWith('2026-11-09T10:00:00', $respuesta->json('data.creada'));
        $this->assertStringStartsWith('2026-11-10T12:30:00', $respuesta->json('data.actualizada'));
    }

    public function test_a_role_without_permissions_has_an_empty_list(): void
    {
        $aplicacion = DB::table('applications')->insertGetId(['name' => 'fuas']);
        DB::table('roles')->insert(['application_id' => $aplicacion, 'name' => 'lector']);
        $this->conPermiso();

        $this->getJson('/api/iam/v1/aplicaciones/fuas')
            ->assertOk()
            ->assertJsonPath('data.permisos', [])
            ->assertJsonPath('data.roles', [['nombre' => 'lector', 'descripcion' => null, 'permisos' => [], 'baja_desde' => null]]);
    }

    public function test_the_name_in_upper_case_finds_the_application(): void
    {
        $this->iamAdmin();
        $this->conPermiso();

        $this->getJson('/api/iam/v1/aplicaciones/IAM-ADMIN')
            ->assertOk()
            ->assertJsonPath('data.nombre', 'iam-admin');
    }

    public function test_an_unknown_application_answers_404(): void
    {
        $this->conPermiso();

        $this->getJson('/api/iam/v1/aplicaciones/no-existe')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'APLICACION_NO_ENCONTRADA');
    }

    public function test_a_person_without_the_permission_gets_403(): void
    {
        $this->iamAdmin();
        UboLogin::actingAs(id: 'a', roles: ['otro'], scopes: ['iam:usuarios.leer']);

        $this->getJson('/api/iam/v1/aplicaciones/iam-admin')
            ->assertForbidden()
            ->assertJsonPath('error', 'ACCESO_DENEGADO');
    }

    public function test_responses_have_no_internal_ids_and_nothing_is_written(): void
    {
        $this->iamAdmin();
        $this->conPermiso();
        $tablas = ['applications', 'oauth_scopes', 'roles', 'oauth_scope_role', 'iam_auditoria'];
        $antes = array_map(fn (string $tabla) => DB::table($tabla)->count(), $tablas);

        $listado = $this->getJson('/api/iam/v1/aplicaciones')->assertOk()->json();
        $detalle = $this->getJson('/api/iam/v1/aplicaciones/iam-admin')->assertOk()->json();

        foreach ([$listado, $detalle] as $json) {
            $claves = [];
            array_walk_recursive($json, function (mixed $valor, string|int $clave) use (&$claves) {
                $claves[] = $clave;
            });
            $this->assertNotContains('id', $claves);
            $this->assertNotContains('application_id', $claves);
        }
        $this->assertSame($antes, array_map(fn (string $tabla) => DB::table($tabla)->count(), $tablas));
    }
}
