<?php

namespace Tests\Feature\Aplicaciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec aplicaciones: permiso para consultar, listado paginado y búsqueda.
 */
class ListarAplicacionesTest extends TestCase
{
    use RefreshDatabase;

    private function conPermiso(): void
    {
        UboLogin::actingAs(id: 'a', roles: ['mesa-de-ayuda'], scopes: ['iam:aplicaciones.leer']);
    }

    private function aplicacion(string $nombre, ?string $descripcion = null, int $permisos = 0, int $roles = 0): void
    {
        $id = DB::table('applications')->insertGetId(['name' => $nombre, 'description' => $descripcion]);

        for ($i = 1; $i <= $permisos; $i++) {
            DB::table('oauth_scopes')->insert(['application_id' => $id, 'name' => "{$nombre}:permiso{$i}", 'description' => "Permiso {$i}"]);
        }
        for ($i = 1; $i <= $roles; $i++) {
            DB::table('roles')->insert(['application_id' => $id, 'name' => "rol{$i}"]);
        }
    }

    public function test_the_help_desk_can_list_the_applications(): void
    {
        $this->aplicacion('pago-honorarios', 'Pago de Honorarios');
        $this->conPermiso();

        $this->getJson('/api/iam/v1/aplicaciones')
            ->assertOk()
            ->assertJsonPath('data.0.nombre', 'pago-honorarios')
            ->assertJsonPath('data.0.descripcion', 'Pago de Honorarios');
    }

    public function test_a_person_without_the_permission_gets_403(): void
    {
        UboLogin::actingAs(id: 'a', roles: ['otro'], scopes: ['iam:usuarios.leer']);

        $this->getJson('/api/iam/v1/aplicaciones')
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'ACCESO_DENEGADO');
    }

    public function test_without_a_session_it_answers_401(): void
    {
        $this->getJson('/api/iam/v1/aplicaciones')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'NO_AUTENTICADO');
    }

    public function test_the_first_page_has_twenty_applications_ordered_by_name(): void
    {
        for ($i = 25; $i >= 1; $i--) {
            $this->aplicacion(sprintf('app-%02d', $i));
        }
        $this->conPermiso();

        $respuesta = $this->getJson('/api/iam/v1/aplicaciones')->assertOk();

        $this->assertCount(20, $respuesta->json('data'));
        $this->assertSame('app-01', $respuesta->json('data.0.nombre'));
        $this->assertSame('app-20', $respuesta->json('data.19.nombre'));
        $respuesta->assertJsonPath('meta', ['total' => 25, 'pagina' => 1, 'por_pagina' => 20]);

        $this->getJson('/api/iam/v1/aplicaciones?pagina=2')
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.pagina', 2);
    }

    public function test_each_application_counts_its_permissions_and_roles(): void
    {
        $this->aplicacion('iam-admin', 'Portal de administración de IAM', permisos: 7, roles: 2);
        $this->aplicacion('fuas', 'FUAS');
        $this->conPermiso();

        $this->getJson('/api/iam/v1/aplicaciones')
            ->assertOk()
            ->assertJsonPath('data', [
                ['nombre' => 'fuas', 'descripcion' => 'FUAS', 'permisos' => 0, 'roles' => 0],
                ['nombre' => 'iam-admin', 'descripcion' => 'Portal de administración de IAM', 'permisos' => 7, 'roles' => 2],
            ]);
    }

    public function test_invalid_pagination_answers_422(): void
    {
        $this->conPermiso();

        foreach (['por_pagina=500' => 'por_pagina', 'pagina=0' => 'pagina'] as $consulta => $campo) {
            $this->getJson("/api/iam/v1/aplicaciones?{$consulta}")
                ->assertUnprocessable()
                ->assertJsonPath('error', 'VALIDACION')
                ->assertJsonStructure(['errores' => [$campo]]);
        }
    }

    public function test_search_by_part_of_the_description_ignoring_case(): void
    {
        $this->aplicacion('pago-honorarios', 'Pago de Honorarios');
        $this->aplicacion('fuas', 'FUAS');
        $this->conPermiso();

        $this->getJson('/api/iam/v1/aplicaciones?buscar=HONOR')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'pago-honorarios');
    }

    public function test_a_like_wildcard_is_searched_as_text(): void
    {
        $this->aplicacion('fuas', 'FUAS');
        $this->aplicacion('descuentos', 'Descuento del 10%');
        $this->conPermiso();

        $this->getJson('/api/iam/v1/aplicaciones?buscar=%25')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'descuentos');
    }
}
