<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\TablasDeApiAuth;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec usuarios: permiso para consultar, listado paginado y búsqueda.
 */
class ListarUsuariosTest extends TestCase
{
    use RefreshDatabase;
    use TablasDeApiAuth;

    private function conPermiso(): void
    {
        UboLogin::actingAs(id: 'a', roles: ['mesa-de-ayuda'], scopes: ['iam:usuarios.leer']);
    }

    /**
     * @param  array<string, mixed>  $columnas
     */
    private function persona(string $nombre, string $correo, array $columnas = []): string
    {
        $uuid = (string) Str::uuid();
        DB::table('users')->insert(['uuid' => $uuid, 'name' => $nombre, 'email' => $correo] + $columnas);

        return $uuid;
    }

    public function test_the_help_desk_can_list_the_people(): void
    {
        $this->persona('Ana Pérez', 'ana@ubo.cl');
        $this->conPermiso();

        $this->getJson('/api/iam/v1/usuarios')
            ->assertOk()
            ->assertJsonPath('data.0.nombre', 'Ana Pérez')
            ->assertJsonPath('data.0.correo', 'ana@ubo.cl')
            ->assertJsonPath('data.0.estado', 'activo');
    }

    public function test_a_person_without_the_permission_gets_403(): void
    {
        UboLogin::actingAs(id: 'a', roles: ['otro'], scopes: ['iam:aplicaciones.leer']);

        $this->getJson('/api/iam/v1/usuarios')
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'ACCESO_DENEGADO');
    }

    public function test_without_a_session_it_answers_401(): void
    {
        $this->getJson('/api/iam/v1/usuarios')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'NO_AUTENTICADO');
    }

    public function test_the_first_page_has_twenty_people_ordered_by_name(): void
    {
        foreach (range(1, 25) as $n) {
            $this->persona(sprintf('Persona %02d', $n), "p{$n}@ubo.cl");
        }
        $this->conPermiso();

        $response = $this->getJson('/api/iam/v1/usuarios')->assertOk();

        $this->assertCount(20, $response->json('data'));
        $this->assertSame('Persona 01', $response->json('data.0.nombre'));
        $this->assertSame('Persona 20', $response->json('data.19.nombre'));
        $response->assertJsonPath('meta', ['total' => 25, 'pagina' => 1, 'por_pagina' => 20]);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->json('data.0.uuid'));
    }

    public function test_the_next_page_has_the_rest(): void
    {
        foreach (range(1, 25) as $n) {
            $this->persona(sprintf('Persona %02d', $n), "p{$n}@ubo.cl");
        }
        $this->conPermiso();

        $response = $this->getJson('/api/iam/v1/usuarios?pagina=2')->assertOk();

        $this->assertCount(5, $response->json('data'));
        $this->assertSame('Persona 21', $response->json('data.0.nombre'));
        $response->assertJsonPath('meta.pagina', 2);
    }

    public function test_an_invalid_pagination_answers_422(): void
    {
        $this->conPermiso();

        $this->getJson('/api/iam/v1/usuarios?por_pagina=500')
            ->assertUnprocessable()
            ->assertJsonPath('error', 'VALIDACION')
            ->assertJsonStructure(['errores' => ['por_pagina']]);

        $this->getJson('/api/iam/v1/usuarios?pagina=0')
            ->assertUnprocessable()
            ->assertJsonStructure(['errores' => ['pagina']]);
    }

    public function test_search_by_part_of_the_name(): void
    {
        $this->persona('Ana Pérez', 'ana@ubo.cl');
        $this->persona('Juan Soto', 'juan@ubo.cl');
        $this->conPermiso();

        $response = $this->getJson('/api/iam/v1/usuarios?buscar='.urlencode('pér'))->assertOk();

        $this->assertSame(['Ana Pérez'], array_column($response->json('data'), 'nombre'));
    }

    public function test_search_by_email_ignores_case(): void
    {
        $this->persona('Ana Pérez', 'ana@ubo.cl');
        $this->persona('Juan Soto', 'juan@ubo.cl');
        $this->conPermiso();

        $response = $this->getJson('/api/iam/v1/usuarios?buscar=ANA@UBO')->assertOk();

        $this->assertSame(['ana@ubo.cl'], array_column($response->json('data'), 'correo'));
    }

    public function test_search_by_rut_with_dots(): void
    {
        $this->persona('Ana Pérez', 'ana@ubo.cl', ['rut' => '12345678-5']);
        $this->persona('Juan Soto', 'juan@ubo.cl', ['rut' => '11111111-1']);
        $this->conPermiso();

        $response = $this->getJson('/api/iam/v1/usuarios?buscar=12.345.678-5')->assertOk();

        $this->assertSame(['12345678-5'], array_column($response->json('data'), 'rut'));
    }

    public function test_only_the_people_who_were_disabled(): void
    {
        $this->persona('Ana Pérez', 'ana@ubo.cl');
        $this->persona('Juan Soto', 'juan@ubo.cl', ['disabled_at' => now()]);
        $this->conPermiso();

        $response = $this->getJson('/api/iam/v1/usuarios?estado=baja')->assertOk();

        $this->assertSame(['Juan Soto'], array_column($response->json('data'), 'nombre'));
        $response->assertJsonPath('data.0.estado', 'baja');
        $this->assertSame(['Ana Pérez'], array_column($this->getJson('/api/iam/v1/usuarios?estado=activos')->json('data'), 'nombre'));
    }

    public function test_an_invalid_state_answers_422(): void
    {
        $this->conPermiso();

        $this->getJson('/api/iam/v1/usuarios?estado=borrados')
            ->assertUnprocessable()
            ->assertJsonPath('error', 'VALIDACION');
    }

    public function test_like_wildcards_are_searched_literally(): void
    {
        $this->persona('Ana Pérez', 'ana@ubo.cl');
        $this->persona('Juan_Soto', 'juan@ubo.cl');
        $this->conPermiso();

        $this->assertSame([], $this->getJson('/api/iam/v1/usuarios?buscar=%25')->json('data'));
        $this->assertSame(['Juan_Soto'], array_column($this->getJson('/api/iam/v1/usuarios?buscar=_')->json('data'), 'nombre'));
    }

    public function test_people_without_activity_for_30_or_90_days_or_never(): void
    {
        $this->crearTablasDeApiAuth();
        $ahora = CarbonImmutable::parse('2026-11-12 12:00:00');
        $this->travelTo($ahora);
        $cliente = (string) Str::uuid();
        foreach (['Ana' => 100, 'Beto' => 45, 'Carla' => 1, 'Eva' => 100] as $nombre => $dias) {
            $this->persona($nombre, strtolower($nombre).'@ubo.cl', $nombre === 'Eva' ? ['disabled_at' => $ahora] : []);
            $id = (int) DB::table('users')->where('name', $nombre)->value('id');
            $this->tokenEmitido($id, $cliente, $ahora->subDays($dias));
        }
        $this->tokenEmitido((int) DB::table('users')->where('name', 'Ana')->value('id'), $cliente, $ahora->subDays(200));
        $this->persona('Dani', 'dani@ubo.cl');
        $this->conPermiso();

        $nombres = fn (string $query) => array_column($this->getJson('/api/iam/v1/usuarios?'.$query)->assertOk()->json('data'), 'nombre');

        $this->assertSame(['Ana', 'Beto'], $nombres('estado=activos&inactivas=30'));
        $this->assertSame(['Ana'], $nombres('estado=activos&inactivas=90'));
        $this->assertSame(['Dani'], $nombres('estado=activos&inactivas=nunca'));
        $this->assertSame(['Ana', 'Eva'], $nombres('inactivas=90'));
        $this->assertSame(['Beto'], $nombres('inactivas=30&buscar=beto'));
    }

    public function test_an_invalid_inactivity_answers_422(): void
    {
        $this->conPermiso();

        $this->getJson('/api/iam/v1/usuarios?inactivas=60')
            ->assertUnprocessable()
            ->assertJsonPath('error', 'VALIDACION');
    }
}
