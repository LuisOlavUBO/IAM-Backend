<?php

namespace Tests\Feature\Aplicaciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CatalogoDePrueba;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;

/**
 * Spec aplicaciones (aplicaciones-catalogo): "Permisos que da un rol" (design D5) y "Catálogo de iam-admin
 * protegido".
 */
class CambiarPermisosDeRolTest extends TestCase
{
    use CatalogoDePrueba;
    use IniciaSesionEnIam;
    use RefreshDatabase;

    private const URL = '/api/iam/v1/aplicaciones/biblioteca/roles/bibliotecario/permisos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearCatalogo();
        $this->permiso($this->ids['biblioteca'], 'biblioteca:prestamos.leer', 'Ver préstamos');
        $this->rol($this->ids['biblioteca'], 'bibliotecario');
    }

    /**
     * @return list<array{accion: string, entidad_id: string, antes: mixed, despues: mixed}>
     */
    private function entradas(): array
    {
        return DB::table('iam_auditoria')->orderBy('id')->get()->map(fn (object $e) => [
            'accion' => $e->accion, 'entidad_id' => $e->entidad_id, 'antes' => json_decode((string) $e->antes, true), 'despues' => json_decode((string) $e->despues, true),
        ])->all();
    }

    public function test_giving_permissions_of_any_application_audits_one_entry_each(): void
    {
        $this->comoAdmin();

        $this->putJson(self::URL, ['permisos' => ['geografia:leer', 'biblioteca:prestamos.leer']])
            ->assertOk()
            ->assertExactJson(['data' => ['permisos' => ['biblioteca:prestamos.leer', 'geografia:leer']]]);

        $this->getJson('/api/iam/v1/aplicaciones/biblioteca')
            ->assertJsonPath('data.roles.0.permisos', ['biblioteca:prestamos.leer', 'geografia:leer']);

        $rol = (string) $this->ids['biblioteca/bibliotecario'];
        $this->assertEqualsCanonicalizing([
            ['accion' => 'rol.permiso.asignacion', 'entidad_id' => $rol, 'antes' => null, 'despues' => ['permiso' => 'biblioteca:prestamos.leer']],
            ['accion' => 'rol.permiso.asignacion', 'entidad_id' => $rol, 'antes' => null, 'despues' => ['permiso' => 'geografia:leer']],
        ], $this->entradas());
        $this->assertSame(['rol'], DB::table('iam_auditoria')->distinct()->pluck('entidad_tipo')->all());
    }

    public function test_removing_a_permission_audits_its_removal(): void
    {
        DB::table('oauth_scope_role')->insert([
            ['role_id' => $this->ids['biblioteca/bibliotecario'], 'oauth_scope_id' => $this->ids['biblioteca:prestamos.leer']],
            ['role_id' => $this->ids['biblioteca/bibliotecario'], 'oauth_scope_id' => $this->ids['geografia:leer']],
        ]);
        $this->comoAdmin();

        $this->putJson(self::URL, ['permisos' => ['biblioteca:prestamos.leer']])
            ->assertOk()
            ->assertExactJson(['data' => ['permisos' => ['biblioteca:prestamos.leer']]]);

        $this->assertSame(['biblioteca:prestamos.leer'], $this->permisosDe('biblioteca/bibliotecario'));
        $this->assertSame([
            ['accion' => 'rol.permiso.retiro', 'entidad_id' => (string) $this->ids['biblioteca/bibliotecario'], 'antes' => ['permiso' => 'geografia:leer'], 'despues' => null],
        ], $this->entradas());
    }

    public function test_the_same_permissions_change_nothing_and_an_empty_list_removes_all(): void
    {
        DB::table('oauth_scope_role')->insert(['role_id' => $this->ids['biblioteca/bibliotecario'], 'oauth_scope_id' => $this->ids['geografia:leer']]);
        $this->comoAdmin();

        $this->putJson(self::URL, ['permisos' => ['GEOGRAFIA:leer', 'geografia:leer']])
            ->assertOk()
            ->assertExactJson(['data' => ['permisos' => ['geografia:leer']]]);
        $this->assertSame(0, DB::table('iam_auditoria')->count());

        $this->putJson(self::URL, ['permisos' => []])->assertOk()->assertExactJson(['data' => ['permisos' => []]]);
        $this->assertSame([], $this->permisosDe('biblioteca/bibliotecario'));
        $this->assertSame(['rol.permiso.retiro'], DB::table('iam_auditoria')->pluck('accion')->all());
    }

    public function test_an_unknown_permission_answers_422_without_changing_anything(): void
    {
        $this->comoAdmin();

        $respuesta = $this->putJson(self::URL, ['permisos' => ['geografia:leer', 'biblioteca:no-existe']])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'VALIDACION');

        $this->assertStringContainsString('biblioteca:no-existe', $respuesta->json('errores.permisos.0'));
        $this->assertSame([], $this->permisosDe('biblioteca/bibliotecario'));
        $this->assertSame(0, DB::table('iam_auditoria')->count());

        $this->putJson(self::URL, [])->assertUnprocessable()->assertJsonStructure(['errores' => ['permisos']]);
    }

    public function test_an_unknown_role_or_application_answers_404(): void
    {
        $this->comoAdmin();

        $this->putJson('/api/iam/v1/aplicaciones/biblioteca/roles/lector/permisos', ['permisos' => []])
            ->assertNotFound()
            ->assertJsonPath('error', 'ROL_NO_ENCONTRADO');
        $this->putJson('/api/iam/v1/aplicaciones/no-existe/roles/bibliotecario/permisos', ['permisos' => []])
            ->assertNotFound()
            ->assertJsonPath('error', 'APLICACION_NO_ENCONTRADA');
    }

    public function test_widening_the_help_desk_is_refused_and_audited(): void
    {
        $this->comoAdmin();

        $this->putJson('/api/iam/v1/aplicaciones/iam-admin/roles/mesa-de-ayuda/permisos', [
            'permisos' => ['iam:aplicaciones.leer', 'iam:usuarios.administrar', 'iam:clientes.administrar'],
        ])
            ->assertConflict()
            ->assertJsonPath('error', 'CATALOGO_DE_IAM_ADMIN');

        $this->assertSame(['iam:aplicaciones.leer', 'iam:usuarios.administrar'], $this->permisosDe('iam-admin/mesa-de-ayuda'));
        $this->rechazoAuditado('rol.permiso.asignacion', 'rol', $this->ids['iam-admin/mesa-de-ayuda']);
    }

    public function test_narrowing_a_role_of_iam_admin_is_refused_and_audited_as_a_removal(): void
    {
        $this->comoAdmin();

        $this->putJson('/api/iam/v1/aplicaciones/iam-admin/roles/administrador/permisos', ['permisos' => ['iam:aplicaciones.leer']])
            ->assertConflict()
            ->assertJsonPath('error', 'CATALOGO_DE_IAM_ADMIN');

        $this->assertCount(3, $this->permisosDe('iam-admin/administrador'));
        $this->rechazoAuditado('rol.permiso.retiro', 'rol', $this->ids['iam-admin/administrador']);
    }

    public function test_a_permission_of_iam_admin_is_not_given_to_another_application(): void
    {
        $this->comoAdmin();

        $this->putJson(self::URL, ['permisos' => ['geografia:leer', 'iam:usuarios.administrar']])
            ->assertConflict()
            ->assertJsonPath('error', 'CATALOGO_DE_IAM_ADMIN');

        $this->assertSame([], $this->permisosDe('biblioteca/bibliotecario'));
        $this->rechazoAuditado('rol.permiso.asignacion', 'rol', $this->ids['biblioteca/bibliotecario']);
    }

    public function test_the_help_desk_gets_403_and_without_a_session_401(): void
    {
        $this->putJson(self::URL, ['permisos' => ['geografia:leer']])->assertUnauthorized()->assertJsonPath('error', 'NO_AUTENTICADO');

        $this->comoMesaDeAyuda();
        $this->putJson(self::URL, ['permisos' => ['geografia:leer']])->assertForbidden()->assertJsonPath('error', 'ACCESO_DENEGADO');

        $this->assertSame([], $this->permisosDe('biblioteca/bibliotecario'));
    }

    public function test_without_the_csrf_token_it_answers_419(): void
    {
        $this->setUpIam();
        $this->enforceCsrf();
        $this->comoAdmin();

        $this->withBrowserSession()->putJson(self::URL, ['permisos' => ['geografia:leer']])->assertStatus(419);

        $this->assertSame([], $this->permisosDe('biblioteca/bibliotecario'));
    }
}
