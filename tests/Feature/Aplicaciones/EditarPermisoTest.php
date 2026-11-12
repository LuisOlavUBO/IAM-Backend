<?php

namespace Tests\Feature\Aplicaciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CatalogoDePrueba;
use Tests\TestCase;

/**
 * Spec aplicaciones (aplicaciones-catalogo): "Crear y describir permisos" (la descripción) y "Catálogo de
 * iam-admin protegido".
 */
class EditarPermisoTest extends TestCase
{
    use CatalogoDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearCatalogo();
    }

    public function test_changing_the_description_audits_before_and_after(): void
    {
        $this->comoAdmin();

        $this->patchJson('/api/iam/v1/aplicaciones/geografia/permisos/geografia:leer', ['descripcion' => 'Leer regiones y comunas'])
            ->assertOk()
            ->assertExactJson(['data' => ['nombre' => 'geografia:leer', 'descripcion' => 'Leer regiones y comunas']]);

        $this->assertSame('Leer regiones y comunas', DB::table('oauth_scopes')->where('name', 'geografia:leer')->value('description'));

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('permiso.edicion', $entrada->accion);
        $this->assertSame('permiso', $entrada->entidad_tipo);
        $this->assertSame((string) $this->ids['geografia:leer'], $entrada->entidad_id);
        $this->assertSame(['descripcion' => 'Descripción de geografia:leer'], json_decode($entrada->antes, true));
        $this->assertSame(['descripcion' => 'Leer regiones y comunas'], json_decode($entrada->despues, true));
    }

    public function test_the_same_description_changes_nothing(): void
    {
        $this->comoAdmin();

        $this->patchJson('/api/iam/v1/aplicaciones/geografia/permisos/geografia:leer', ['descripcion' => 'Descripción de geografia:leer'])->assertOk();

        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_the_description_is_required(): void
    {
        $this->comoAdmin();

        $this->patchJson('/api/iam/v1/aplicaciones/geografia/permisos/geografia:leer', ['descripcion' => ''])
            ->assertUnprocessable()
            ->assertJsonStructure(['errores' => ['descripcion']]);
    }

    public function test_an_unknown_permission_or_one_of_another_application_answers_404(): void
    {
        $this->comoAdmin();

        foreach (['/api/iam/v1/aplicaciones/geografia/permisos/geografia:escribir', '/api/iam/v1/aplicaciones/biblioteca/permisos/geografia:leer'] as $url) {
            $this->patchJson($url, ['descripcion' => 'x'])
                ->assertNotFound()
                ->assertJsonPath('error', 'PERMISO_NO_ENCONTRADO');
        }

        $this->patchJson('/api/iam/v1/aplicaciones/no-existe/permisos/geografia:leer', ['descripcion' => 'x'])
            ->assertNotFound()
            ->assertJsonPath('error', 'APLICACION_NO_ENCONTRADA');
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_a_permission_of_iam_admin_is_not_edited_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->patchJson('/api/iam/v1/aplicaciones/iam-admin/permisos/iam:usuarios.administrar', ['descripcion' => 'x'])
            ->assertConflict()
            ->assertJsonPath('error', 'CATALOGO_DE_IAM_ADMIN');

        $this->assertSame('Descripción de iam:usuarios.administrar', DB::table('oauth_scopes')->where('name', 'iam:usuarios.administrar')->value('description'));
        $this->rechazoAuditado('permiso.edicion', 'permiso', $this->ids['iam:usuarios.administrar']);
    }

    public function test_the_help_desk_gets_403_and_without_a_session_401(): void
    {
        $url = '/api/iam/v1/aplicaciones/geografia/permisos/geografia:leer';
        $this->patchJson($url, ['descripcion' => 'x'])->assertUnauthorized();

        $this->comoMesaDeAyuda();
        $this->patchJson($url, ['descripcion' => 'x'])->assertForbidden()->assertJsonPath('error', 'ACCESO_DENEGADO');
    }
}
