<?php

namespace Tests\Feature\Aplicaciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CatalogoDePrueba;
use Tests\TestCase;

/**
 * Spec aplicaciones (aplicaciones-catalogo): "Crear y describir roles" (el alta) y "Catálogo de iam-admin
 * protegido".
 */
class CrearRolTest extends TestCase
{
    use CatalogoDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearCatalogo();
    }

    public function test_a_role_of_the_library_is_created_without_permissions_and_audited(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/aplicaciones/biblioteca/roles', ['nombre' => 'bibliotecario', 'descripcion' => 'Presta libros'])
            ->assertCreated()
            ->assertExactJson(['data' => ['nombre' => 'bibliotecario', 'descripcion' => 'Presta libros', 'permisos' => []]]);

        $this->getJson('/api/iam/v1/aplicaciones/biblioteca')
            ->assertJsonPath('data.roles', [['nombre' => 'bibliotecario', 'descripcion' => 'Presta libros', 'permisos' => [], 'baja_desde' => null]]);

        $id = (int) DB::table('roles')->where('name', 'bibliotecario')->value('id');
        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('rol.creacion', $entrada->accion);
        $this->assertSame('rol', $entrada->entidad_tipo);
        $this->assertSame((string) $id, $entrada->entidad_id);
        $this->assertSame('ok', $entrada->resultado);
        $this->assertSame(['nombre' => 'biblioteca/bibliotecario', 'descripcion' => 'Presta libros'], json_decode($entrada->despues, true));
    }

    public function test_capitals_and_spaces_are_allowed_and_the_description_is_optional(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/aplicaciones/biblioteca/roles', ['nombre' => 'Jefe de biblioteca'])
            ->assertCreated()
            ->assertJsonPath('data.descripcion', null);
    }

    public function test_a_bad_name_answers_422(): void
    {
        $this->comoAdmin();

        foreach (['biblioteca/lector', '-lector', 'lector:x', ''] as $nombre) {
            $this->postJson('/api/iam/v1/aplicaciones/biblioteca/roles', ['nombre' => $nombre])
                ->assertUnprocessable()
                ->assertJsonStructure(['errores' => ['nombre']]);
        }

        $this->assertSame(0, DB::table('roles')->where('application_id', $this->ids['biblioteca'])->count());
    }

    public function test_the_name_is_unique_within_the_application_without_distinguishing_case(): void
    {
        $this->rol($this->ids['biblioteca'], 'Lector');
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/aplicaciones/biblioteca/roles', ['nombre' => 'lector'])
            ->assertConflict()
            ->assertJsonPath('error', 'ROL_EXISTENTE');

        // Another application may have a role with the same name.
        $this->postJson('/api/iam/v1/aplicaciones/geografia/roles', ['nombre' => 'lector'])->assertCreated();

        $this->assertSame(1, DB::table('roles')->where('application_id', $this->ids['biblioteca'])->count());
    }

    public function test_an_unknown_application_answers_404(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/aplicaciones/no-existe/roles', ['nombre' => 'lector'])
            ->assertNotFound()
            ->assertJsonPath('error', 'APLICACION_NO_ENCONTRADA');
    }

    public function test_no_role_is_created_in_iam_admin_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/aplicaciones/iam-admin/roles', ['nombre' => 'superusuario'])
            ->assertConflict()
            ->assertJsonPath('error', 'CATALOGO_DE_IAM_ADMIN');

        $this->assertFalse(DB::table('roles')->where('name', 'superusuario')->exists());
        $this->rechazoAuditado('rol.creacion', 'aplicacion', $this->ids['iam-admin']);
    }

    public function test_the_help_desk_gets_403_and_without_a_session_401(): void
    {
        $this->postJson('/api/iam/v1/aplicaciones/biblioteca/roles', ['nombre' => 'lector'])->assertUnauthorized();

        $this->comoMesaDeAyuda();
        $this->postJson('/api/iam/v1/aplicaciones/biblioteca/roles', ['nombre' => 'lector'])->assertForbidden()->assertJsonPath('error', 'ACCESO_DENEGADO');

        $this->assertSame(0, DB::table('roles')->where('application_id', $this->ids['biblioteca'])->count());
    }
}
