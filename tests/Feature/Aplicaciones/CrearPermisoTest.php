<?php

namespace Tests\Feature\Aplicaciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CatalogoDePrueba;
use Tests\TestCase;

/**
 * Spec aplicaciones (aplicaciones-catalogo): "Crear y describir permisos" (el alta) y "Catálogo de iam-admin
 * protegido".
 */
class CrearPermisoTest extends TestCase
{
    use CatalogoDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearCatalogo();
    }

    public function test_a_permission_of_the_library_is_created_and_audited_as_iam_install_does(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/aplicaciones/biblioteca/permisos', ['nombre' => 'biblioteca:prestamos.leer', 'descripcion' => 'Ver préstamos'])
            ->assertCreated()
            ->assertExactJson(['data' => ['nombre' => 'biblioteca:prestamos.leer', 'descripcion' => 'Ver préstamos']]);

        $this->getJson('/api/iam/v1/aplicaciones/biblioteca')
            ->assertJsonPath('data.permisos', [['nombre' => 'biblioteca:prestamos.leer', 'descripcion' => 'Ver préstamos']]);

        $id = (int) DB::table('oauth_scopes')->where('name', 'biblioteca:prestamos.leer')->value('id');
        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('permiso.creacion', $entrada->accion);
        $this->assertSame('permiso', $entrada->entidad_tipo);
        $this->assertSame((string) $id, $entrada->entidad_id);
        $this->assertSame('ok', $entrada->resultado);
        $this->assertSame(['nombre' => 'biblioteca:prestamos.leer', 'aplicacion' => 'biblioteca', 'descripcion' => 'Ver préstamos'], json_decode($entrada->despues, true));
    }

    public function test_a_bad_name_or_a_missing_description_answers_422(): void
    {
        $this->comoAdmin();

        foreach (['Biblioteca:leer', ':leer', 'leer todo', 'a/b'] as $nombre) {
            $this->postJson('/api/iam/v1/aplicaciones/biblioteca/permisos', ['nombre' => $nombre, 'descripcion' => 'x'])
                ->assertUnprocessable()
                ->assertJsonStructure(['errores' => ['nombre']]);
        }

        $this->postJson('/api/iam/v1/aplicaciones/biblioteca/permisos', ['nombre' => 'biblioteca:leer'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errores' => ['descripcion']]);

        $this->assertSame(0, DB::table('oauth_scopes')->where('application_id', $this->ids['biblioteca'])->count());
    }

    public function test_the_name_is_unique_in_all_of_iam(): void
    {
        $this->comoAdmin();

        // geografia:leer belongs to another application.
        $this->postJson('/api/iam/v1/aplicaciones/biblioteca/permisos', ['nombre' => 'geografia:leer', 'descripcion' => 'x'])
            ->assertConflict()
            ->assertJsonPath('error', 'PERMISO_EXISTENTE');

        $this->assertSame(0, DB::table('oauth_scopes')->where('application_id', $this->ids['biblioteca'])->count());
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_an_unknown_application_answers_404(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/aplicaciones/no-existe/permisos', ['nombre' => 'x:leer', 'descripcion' => 'x'])
            ->assertNotFound()
            ->assertJsonPath('error', 'APLICACION_NO_ENCONTRADA');
    }

    public function test_no_permission_is_created_in_iam_admin_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/aplicaciones/iam-admin/permisos', ['nombre' => 'iam:todo', 'descripcion' => 'Todo'])
            ->assertConflict()
            ->assertJsonPath('error', 'CATALOGO_DE_IAM_ADMIN');

        $this->assertFalse(DB::table('oauth_scopes')->where('name', 'iam:todo')->exists());
        $this->rechazoAuditado('permiso.creacion', 'aplicacion', $this->ids['iam-admin']);
    }

    public function test_the_help_desk_gets_403_and_without_a_session_401(): void
    {
        $cuerpo = ['nombre' => 'biblioteca:leer', 'descripcion' => 'x'];
        $this->postJson('/api/iam/v1/aplicaciones/biblioteca/permisos', $cuerpo)->assertUnauthorized();

        $this->comoMesaDeAyuda();
        $this->postJson('/api/iam/v1/aplicaciones/biblioteca/permisos', $cuerpo)->assertForbidden()->assertJsonPath('error', 'ACCESO_DENEGADO');

        $this->assertFalse(DB::table('oauth_scopes')->where('name', 'biblioteca:leer')->exists());
    }
}
