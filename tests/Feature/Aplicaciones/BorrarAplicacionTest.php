<?php

namespace Tests\Feature\Aplicaciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CatalogoDePrueba;
use Tests\TestCase;

/**
 * Spec aplicaciones (aplicaciones-renombrar-y-borrar): "Borrar lo que no está en uso" y "Catálogo de iam-admin
 * sin borrados", para una aplicación.
 */
class BorrarAplicacionTest extends TestCase
{
    use CatalogoDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearCatalogo();
    }

    public function test_an_empty_application_is_deleted_and_audited(): void
    {
        $this->comoAdmin();

        $this->deleteJson('/api/iam/v1/aplicaciones/biblioteca')
            ->assertOk()
            ->assertExactJson(['data' => ['borrado' => true]]);

        $this->assertFalse(DB::table('applications')->where('name', 'biblioteca')->exists());

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('aplicacion.borrado', $entrada->accion);
        $this->assertSame((string) $this->ids['biblioteca'], $entrada->entidad_id);
        $this->assertSame(['nombre' => 'biblioteca', 'descripcion' => 'Sistema de biblioteca'], json_decode($entrada->antes, true));
    }

    public function test_an_application_with_permissions_roles_or_clients_is_in_use(): void
    {
        $this->comoAdmin();
        $this->cliente('biblioteca');

        $this->deleteJson('/api/iam/v1/aplicaciones/geografia')
            ->assertConflict()
            ->assertJsonPath('error', 'EN_USO')
            ->assertJsonPath('impacto', ['permisos' => 1, 'roles' => 0, 'clientes' => 0]);

        $this->deleteJson('/api/iam/v1/aplicaciones/biblioteca')
            ->assertConflict()
            ->assertJsonPath('impacto', ['permisos' => 0, 'roles' => 0, 'clientes' => 1]);

        $this->assertSame(3, DB::table('applications')->count());
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_iam_admin_is_not_deleted_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->deleteJson('/api/iam/v1/aplicaciones/iam-admin')
            ->assertConflict()
            ->assertJsonPath('error', 'CATALOGO_DE_IAM_ADMIN');

        $this->rechazoAuditado('aplicacion.borrado', 'aplicacion', $this->ids['iam-admin']);
    }

    public function test_an_unknown_application_answers_404_and_the_help_desk_403(): void
    {
        $this->comoAdmin();
        $this->deleteJson('/api/iam/v1/aplicaciones/no-existe')
            ->assertNotFound()
            ->assertJsonPath('error', 'APLICACION_NO_ENCONTRADA');

        $this->comoMesaDeAyuda();
        $this->deleteJson('/api/iam/v1/aplicaciones/biblioteca')->assertForbidden();
        $this->assertTrue(DB::table('applications')->where('name', 'biblioteca')->exists());
    }
}
