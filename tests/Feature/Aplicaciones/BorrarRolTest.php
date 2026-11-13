<?php

namespace Tests\Feature\Aplicaciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CatalogoDePrueba;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;

/**
 * Spec aplicaciones (aplicaciones-renombrar-y-borrar): "Borrar lo que no está en uso" y "Catálogo de iam-admin
 * sin borrados", para un rol.
 */
class BorrarRolTest extends TestCase
{
    use CatalogoDePrueba;
    use IniciaSesionEnIam;
    use RefreshDatabase;

    private const URL = '/api/iam/v1/aplicaciones/biblioteca/roles/bibliotecaro';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearCatalogo();
        $this->permiso($this->ids['biblioteca'], 'biblioteca:prestamo.leer');
        $this->rol($this->ids['biblioteca'], 'bibliotecaro', ['biblioteca:prestamo.leer'], 'Por error');
    }

    public function test_a_role_created_by_mistake_is_deleted_without_its_permissions_and_audited(): void
    {
        $this->comoAdmin();

        $this->deleteJson(self::URL)
            ->assertOk()
            ->assertExactJson(['data' => ['borrado' => true]]);

        $this->assertFalse(DB::table('roles')->where('id', $this->ids['biblioteca/bibliotecaro'])->exists());
        $this->assertSame(0, DB::table('oauth_scope_role')->where('role_id', $this->ids['biblioteca/bibliotecaro'])->count());
        $this->assertTrue(DB::table('oauth_scopes')->where('name', 'biblioteca:prestamo.leer')->exists());

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('rol.borrado', $entrada->accion);
        $this->assertSame('rol', $entrada->entidad_tipo);
        $this->assertSame((string) $this->ids['biblioteca/bibliotecaro'], $entrada->entidad_id);
        $this->assertSame(['nombre' => 'biblioteca/bibliotecaro', 'descripcion' => 'Por error'], json_decode($entrada->antes, true));
        $this->assertNull($entrada->despues);
    }

    public function test_a_role_a_person_or_client_has_is_in_use(): void
    {
        $this->comoAdmin();
        $this->persona(['biblioteca/bibliotecaro']);
        $this->cliente('biblioteca', ['biblioteca/bibliotecaro']);

        $this->deleteJson(self::URL)
            ->assertConflict()
            ->assertJsonPath('error', 'EN_USO')
            ->assertJsonPath('impacto', ['personas' => 1, 'clientes' => 1, 'permisos' => ['biblioteca:prestamo.leer']]);

        $this->assertTrue(DB::table('roles')->where('id', $this->ids['biblioteca/bibliotecaro'])->exists());
        $this->assertSame(['biblioteca:prestamo.leer'], $this->permisosDe('biblioteca/bibliotecaro'));
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_a_role_of_iam_admin_is_not_deleted_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->deleteJson('/api/iam/v1/aplicaciones/iam-admin/roles/mesa-de-ayuda')
            ->assertConflict()
            ->assertJsonPath('error', 'CATALOGO_DE_IAM_ADMIN');

        $this->assertTrue(DB::table('roles')->where('id', $this->ids['iam-admin/mesa-de-ayuda'])->exists());
        $this->rechazoAuditado('rol.borrado', 'rol', $this->ids['iam-admin/mesa-de-ayuda']);
    }

    public function test_an_unknown_role_answers_404(): void
    {
        $this->comoAdmin();

        $this->deleteJson('/api/iam/v1/aplicaciones/biblioteca/roles/no-existe')
            ->assertNotFound()
            ->assertJsonPath('error', 'ROL_NO_ENCONTRADO');
    }

    public function test_the_help_desk_gets_403_without_a_session_401_and_without_csrf_419(): void
    {
        $this->deleteJson(self::URL)->assertUnauthorized();

        $this->comoMesaDeAyuda();
        $this->deleteJson(self::URL)->assertForbidden()->assertJsonPath('error', 'ACCESO_DENEGADO');

        $this->setUpIam();
        $this->enforceCsrf();
        $this->comoAdmin();
        $this->withBrowserSession()->deleteJson(self::URL)->assertStatus(419);

        $this->assertTrue(DB::table('roles')->where('id', $this->ids['biblioteca/bibliotecaro'])->exists());
    }
}
