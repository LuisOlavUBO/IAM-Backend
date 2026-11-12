<?php

namespace Tests\Feature\Aplicaciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CatalogoDePrueba;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;

/**
 * Spec aplicaciones (aplicaciones-catalogo): "Crear y describir aplicaciones" (el alta) y "Permiso para
 * administrar el catálogo".
 */
class CrearAplicacionTest extends TestCase
{
    use CatalogoDePrueba;
    use IniciaSesionEnIam;
    use RefreshDatabase;

    public function test_registering_a_new_system_creates_it_empty_and_audits_it(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/aplicaciones', ['nombre' => 'biblioteca', 'descripcion' => 'Sistema de biblioteca'])
            ->assertCreated()
            ->assertExactJson(['data' => ['nombre' => 'biblioteca', 'descripcion' => 'Sistema de biblioteca']]);

        $this->getJson('/api/iam/v1/aplicaciones/biblioteca')
            ->assertOk()
            ->assertJsonPath('data.descripcion', 'Sistema de biblioteca')
            ->assertJsonPath('data.permisos', [])
            ->assertJsonPath('data.roles', []);

        $id = (int) DB::table('applications')->where('name', 'biblioteca')->value('id');
        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('aplicacion.creacion', $entrada->accion);
        $this->assertSame('aplicacion', $entrada->entidad_tipo);
        $this->assertSame((string) $id, $entrada->entidad_id);
        $this->assertSame('ok', $entrada->resultado);
        $this->assertNull($entrada->antes);
        $this->assertSame(['nombre' => 'biblioteca', 'descripcion' => 'Sistema de biblioteca'], json_decode($entrada->despues, true));
    }

    public function test_the_description_is_optional(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/aplicaciones', ['nombre' => 'fuas'])
            ->assertCreated()
            ->assertExactJson(['data' => ['nombre' => 'fuas', 'descripcion' => null]]);
    }

    public function test_a_name_that_is_not_a_lowercase_slug_answers_422(): void
    {
        $this->comoAdmin();

        foreach (['Biblioteca', 'mi biblioteca', 'biblioteca_2', '-biblioteca', str_repeat('a', 101)] as $nombre) {
            $this->postJson('/api/iam/v1/aplicaciones', ['nombre' => $nombre])
                ->assertUnprocessable()
                ->assertJsonPath('error', 'VALIDACION')
                ->assertJsonStructure(['errores' => ['nombre']]);
        }

        $this->postJson('/api/iam/v1/aplicaciones', [])->assertUnprocessable()->assertJsonStructure(['errores' => ['nombre']]);
        $this->assertSame(0, DB::table('applications')->count());
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_a_repeated_name_answers_409(): void
    {
        $this->crearCatalogo();
        $this->comoAdmin();

        foreach (['biblioteca', 'iam-admin'] as $nombre) {
            $this->postJson('/api/iam/v1/aplicaciones', ['nombre' => $nombre])
                ->assertConflict()
                ->assertHeader('Content-Type', 'application/problem+json')
                ->assertJsonPath('error', 'APLICACION_EXISTENTE');
        }

        $this->assertSame(1, DB::table('applications')->where('name', 'biblioteca')->count());
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_the_help_desk_gets_403_and_nothing_is_created(): void
    {
        $this->comoMesaDeAyuda();

        $this->postJson('/api/iam/v1/aplicaciones', ['nombre' => 'biblioteca'])
            ->assertForbidden()
            ->assertJsonPath('error', 'ACCESO_DENEGADO');

        $this->assertSame(0, DB::table('applications')->count());
    }

    public function test_without_a_session_it_answers_401(): void
    {
        $this->postJson('/api/iam/v1/aplicaciones', ['nombre' => 'biblioteca'])
            ->assertUnauthorized()
            ->assertJsonPath('error', 'NO_AUTENTICADO');

        $this->assertSame(0, DB::table('applications')->count());
    }

    public function test_without_the_csrf_token_it_answers_419(): void
    {
        $this->setUpIam();
        $this->enforceCsrf();
        $this->comoAdmin();

        $this->withBrowserSession()->postJson('/api/iam/v1/aplicaciones', ['nombre' => 'biblioteca'])->assertStatus(419);

        $this->assertSame(0, DB::table('applications')->count());
    }
}
