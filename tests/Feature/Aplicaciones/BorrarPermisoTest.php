<?php

namespace Tests\Feature\Aplicaciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CatalogoDePrueba;
use Tests\TestCase;

/**
 * Spec aplicaciones (aplicaciones-renombrar-y-borrar): "Borrar lo que no está en uso" y "Catálogo de iam-admin
 * sin borrados", para un permiso.
 */
class BorrarPermisoTest extends TestCase
{
    use CatalogoDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearCatalogo();
    }

    public function test_a_permission_no_role_grants_is_deleted_and_audited(): void
    {
        $this->comoAdmin();

        $this->deleteJson('/api/iam/v1/aplicaciones/geografia/permisos/geografia:leer')
            ->assertOk()
            ->assertExactJson(['data' => ['borrado' => true]]);

        $this->assertFalse(DB::table('oauth_scopes')->where('name', 'geografia:leer')->exists());

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('permiso.borrado', $entrada->accion);
        $this->assertSame((string) $this->ids['geografia:leer'], $entrada->entidad_id);
        $this->assertSame(['nombre' => 'geografia:leer', 'descripcion' => 'Descripción de geografia:leer'], json_decode($entrada->antes, true));
    }

    public function test_a_permission_some_role_grants_is_in_use(): void
    {
        $this->comoAdmin();
        $this->rol($this->ids['biblioteca'], 'lector', ['geografia:leer']);
        $this->persona(['biblioteca/lector']);

        $this->deleteJson('/api/iam/v1/aplicaciones/geografia/permisos/geografia:leer')
            ->assertConflict()
            ->assertJsonPath('error', 'EN_USO')
            ->assertJsonPath('impacto', ['roles' => ['biblioteca/lector'], 'personas' => 1, 'clientes' => 0]);

        $this->assertTrue(DB::table('oauth_scopes')->where('name', 'geografia:leer')->exists());
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_a_permission_of_iam_admin_is_not_deleted_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->deleteJson('/api/iam/v1/aplicaciones/iam-admin/permisos/iam:clientes.administrar')
            ->assertConflict()
            ->assertJsonPath('error', 'CATALOGO_DE_IAM_ADMIN');

        $this->rechazoAuditado('permiso.borrado', 'permiso', $this->ids['iam:clientes.administrar']);
    }

    public function test_a_permission_of_another_application_answers_404_and_the_help_desk_403(): void
    {
        $this->comoAdmin();
        $this->deleteJson('/api/iam/v1/aplicaciones/biblioteca/permisos/geografia:leer')
            ->assertNotFound()
            ->assertJsonPath('error', 'PERMISO_NO_ENCONTRADO');

        $this->comoMesaDeAyuda();
        $this->deleteJson('/api/iam/v1/aplicaciones/geografia/permisos/geografia:leer')->assertForbidden();
        $this->assertTrue(DB::table('oauth_scopes')->where('name', 'geografia:leer')->exists());
    }
}
