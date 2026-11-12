<?php

namespace Tests\Feature\Aplicaciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CatalogoDePrueba;
use Tests\TestCase;

/**
 * Spec aplicaciones (aplicaciones-catalogo): "Crear y describir roles" (la descripción) y "Catálogo de
 * iam-admin protegido".
 */
class EditarRolTest extends TestCase
{
    use CatalogoDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearCatalogo();
        $this->rol($this->ids['biblioteca'], 'Jefe de biblioteca', [], 'Dirige');
    }

    public function test_changing_the_description_audits_before_and_after(): void
    {
        $this->comoAdmin();

        $this->patchJson('/api/iam/v1/aplicaciones/biblioteca/roles/'.rawurlencode('jefe de biblioteca'), ['descripcion' => 'Dirige la biblioteca'])
            ->assertOk()
            ->assertExactJson(['data' => ['nombre' => 'Jefe de biblioteca', 'descripcion' => 'Dirige la biblioteca']]);

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('rol.edicion', $entrada->accion);
        $this->assertSame('rol', $entrada->entidad_tipo);
        $this->assertSame((string) $this->ids['biblioteca/Jefe de biblioteca'], $entrada->entidad_id);
        $this->assertSame(['descripcion' => 'Dirige'], json_decode($entrada->antes, true));
        $this->assertSame(['descripcion' => 'Dirige la biblioteca'], json_decode($entrada->despues, true));
    }

    public function test_the_same_description_changes_nothing_and_it_can_be_cleared(): void
    {
        $this->comoAdmin();
        $url = '/api/iam/v1/aplicaciones/biblioteca/roles/'.rawurlencode('Jefe de biblioteca');

        $this->patchJson($url, ['descripcion' => 'Dirige'])->assertOk();
        $this->assertSame(0, DB::table('iam_auditoria')->count());

        $this->patchJson($url, ['descripcion' => null])->assertOk()->assertJsonPath('data.descripcion', null);
        $this->assertNull(DB::table('roles')->where('id', $this->ids['biblioteca/Jefe de biblioteca'])->value('description'));
    }

    public function test_an_unknown_role_or_application_answers_404(): void
    {
        $this->comoAdmin();

        // mesa-de-ayuda exists, but in iam-admin.
        $this->patchJson('/api/iam/v1/aplicaciones/biblioteca/roles/mesa-de-ayuda', ['descripcion' => 'x'])
            ->assertNotFound()
            ->assertJsonPath('error', 'ROL_NO_ENCONTRADO');
        $this->patchJson('/api/iam/v1/aplicaciones/no-existe/roles/lector', ['descripcion' => 'x'])
            ->assertNotFound()
            ->assertJsonPath('error', 'APLICACION_NO_ENCONTRADA');
    }

    public function test_a_role_of_iam_admin_is_not_edited_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->patchJson('/api/iam/v1/aplicaciones/iam-admin/roles/mesa-de-ayuda', ['descripcion' => 'x'])
            ->assertConflict()
            ->assertJsonPath('error', 'CATALOGO_DE_IAM_ADMIN');

        $this->assertNull(DB::table('roles')->where('id', $this->ids['iam-admin/mesa-de-ayuda'])->value('description'));
        $this->rechazoAuditado('rol.edicion', 'rol', $this->ids['iam-admin/mesa-de-ayuda']);
    }

    public function test_the_help_desk_gets_403_and_without_a_session_401(): void
    {
        $url = '/api/iam/v1/aplicaciones/biblioteca/roles/'.rawurlencode('Jefe de biblioteca');
        $this->patchJson($url, ['descripcion' => 'x'])->assertUnauthorized();

        $this->comoMesaDeAyuda();
        $this->patchJson($url, ['descripcion' => 'x'])->assertForbidden()->assertJsonPath('error', 'ACCESO_DENEGADO');
    }
}
