<?php

namespace Tests\Feature\Clientes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec clientes: permiso para consultar, listado paginado y búsqueda.
 */
class ListarClientesTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> */
    private array $aplicaciones = [];

    private function conPermiso(): void
    {
        UboLogin::actingAs(id: 'a', roles: ['administrador'], scopes: ['iam:clientes.leer']);
    }

    private function aplicacion(string $nombre): int
    {
        return $this->aplicaciones[$nombre] ??= DB::table('applications')->insertGetId(['name' => $nombre]);
    }

    /**
     * An OAuth client as iam:install or API-Auth would leave it, with $roles roles of its application.
     */
    private function cliente(string $nombre, string $aplicacion = 'iam-admin', bool $revocado = false, int $roles = 0, ?string $id = null): string
    {
        $id ??= Str::lower((string) Str::uuid7());
        $aplicacionId = $this->aplicacion($aplicacion);

        DB::table('oauth_clients')->insert([
            'id' => $id, 'application_id' => $aplicacionId, 'name' => $nombre, 'secret' => 'hash',
            'redirect_uris' => '[]', 'grant_types' => '["client_credentials"]', 'revoked' => $revocado,
        ]);

        for ($i = 1; $i <= $roles; $i++) {
            $rol = DB::table('roles')->insertGetId(['application_id' => $aplicacionId, 'name' => "{$nombre}-rol{$i}"]);
            DB::table('oauth_client_role')->insert(['client_id' => $id, 'role_id' => $rol]);
        }

        return $id;
    }

    public function test_an_administrator_can_list_the_clients(): void
    {
        $id = $this->cliente('iam-admin-portal');
        $this->conPermiso();

        $this->getJson('/api/iam/v1/clientes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.nombre', 'iam-admin-portal');
    }

    public function test_a_person_without_the_permission_gets_403(): void
    {
        UboLogin::actingAs(id: 'a', roles: ['mesa-de-ayuda'], scopes: ['iam:usuarios.leer']);

        $this->getJson('/api/iam/v1/clientes')
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'ACCESO_DENEGADO');
    }

    public function test_without_a_session_it_answers_401(): void
    {
        $this->getJson('/api/iam/v1/clientes')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'NO_AUTENTICADO');
    }

    public function test_the_first_page_has_twenty_clients_ordered_by_name(): void
    {
        for ($i = 25; $i >= 1; $i--) {
            $this->cliente(sprintf('cliente-%02d', $i));
        }
        $this->conPermiso();

        $respuesta = $this->getJson('/api/iam/v1/clientes')->assertOk();

        $this->assertCount(20, $respuesta->json('data'));
        $this->assertSame('cliente-01', $respuesta->json('data.0.nombre'));
        $this->assertSame('cliente-20', $respuesta->json('data.19.nombre'));
        $respuesta->assertJsonPath('meta', ['total' => 25, 'pagina' => 1, 'por_pagina' => 20]);

        $this->getJson('/api/iam/v1/clientes?pagina=2')
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.pagina', 2);
    }

    public function test_each_client_has_its_application_revocation_and_role_count(): void
    {
        $sistema = $this->cliente('iam-admin-sistema', roles: 2);
        $viejo = $this->cliente('antiguo', aplicacion: 'fuas', revocado: true);
        $this->conPermiso();

        $this->getJson('/api/iam/v1/clientes')
            ->assertOk()
            ->assertJsonPath('data', [
                ['id' => $viejo, 'nombre' => 'antiguo', 'aplicacion' => 'fuas', 'revocado' => true, 'roles' => 0],
                ['id' => $sistema, 'nombre' => 'iam-admin-sistema', 'aplicacion' => 'iam-admin', 'revocado' => false, 'roles' => 2],
            ]);
    }

    public function test_invalid_pagination_answers_422(): void
    {
        $this->conPermiso();

        foreach (['por_pagina=500' => 'por_pagina', 'pagina=0' => 'pagina'] as $consulta => $campo) {
            $this->getJson("/api/iam/v1/clientes?{$consulta}")
                ->assertUnprocessable()
                ->assertJsonPath('error', 'VALIDACION')
                ->assertJsonStructure(['errores' => [$campo]]);
        }
    }

    public function test_search_by_application_ignoring_case(): void
    {
        $this->cliente('portal', aplicacion: 'pago-honorarios');
        $this->cliente('iam-admin-portal');
        $this->conPermiso();

        $this->getJson('/api/iam/v1/clientes?buscar=HONOR')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.aplicacion', 'pago-honorarios');
    }

    public function test_search_by_id_in_upper_case(): void
    {
        $id = $this->cliente('iam-admin-sistema', id: '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b');
        $this->cliente('iam-admin-portal');
        $this->conPermiso();

        $this->getJson('/api/iam/v1/clientes?buscar=0199A1B2-C3D4-7E5F-8A9B-0C1D2E3F4A5B')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);
    }

    public function test_a_like_wildcard_is_searched_as_text(): void
    {
        $this->cliente('portal');
        $this->cliente('descuento 10%');
        $this->conPermiso();

        $this->getJson('/api/iam/v1/clientes?buscar=%25')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'descuento 10%');
    }
}
