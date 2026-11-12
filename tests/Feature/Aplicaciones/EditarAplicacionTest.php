<?php

namespace Tests\Feature\Aplicaciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CatalogoDePrueba;
use Tests\TestCase;

/**
 * Spec aplicaciones (aplicaciones-catalogo): "Crear y describir aplicaciones" (la descripción) y "Catálogo
 * de iam-admin protegido".
 */
class EditarAplicacionTest extends TestCase
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

        $this->patchJson('/api/iam/v1/aplicaciones/BIBLIOTECA', ['descripcion' => 'Préstamos de libros'])
            ->assertOk()
            ->assertExactJson(['data' => ['nombre' => 'biblioteca', 'descripcion' => 'Préstamos de libros']]);

        $this->assertSame('Préstamos de libros', DB::table('applications')->where('name', 'biblioteca')->value('description'));
        $this->assertNotSame('2026-01-01 00:00:00.000', DB::table('applications')->where('name', 'biblioteca')->value('updated_at'));

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('aplicacion.edicion', $entrada->accion);
        $this->assertSame('aplicacion', $entrada->entidad_tipo);
        $this->assertSame((string) $this->ids['biblioteca'], $entrada->entidad_id);
        $this->assertSame('ok', $entrada->resultado);
        $this->assertSame(['descripcion' => 'Sistema de biblioteca'], json_decode($entrada->antes, true));
        $this->assertSame(['descripcion' => 'Préstamos de libros'], json_decode($entrada->despues, true));
    }

    public function test_the_description_can_be_cleared(): void
    {
        $this->comoAdmin();

        $this->patchJson('/api/iam/v1/aplicaciones/biblioteca', ['descripcion' => ''])
            ->assertOk()
            ->assertJsonPath('data.descripcion', null);

        $this->assertNull(DB::table('applications')->where('name', 'biblioteca')->value('description'));
    }

    public function test_the_same_description_changes_nothing(): void
    {
        $this->comoAdmin();

        $this->patchJson('/api/iam/v1/aplicaciones/biblioteca', ['descripcion' => 'Sistema de biblioteca'])->assertOk();

        $this->assertSame('2026-01-01 00:00:00.000', DB::table('applications')->where('name', 'biblioteca')->value('updated_at'));
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_without_the_description_it_answers_422(): void
    {
        $this->comoAdmin();

        $this->patchJson('/api/iam/v1/aplicaciones/biblioteca', ['nombre' => 'otra'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errores' => ['descripcion']]);

        $this->assertSame('biblioteca', DB::table('applications')->where('id', $this->ids['biblioteca'])->value('name'));
    }

    public function test_an_unknown_application_answers_404(): void
    {
        $this->comoAdmin();

        $this->patchJson('/api/iam/v1/aplicaciones/no-existe', ['descripcion' => 'x'])
            ->assertNotFound()
            ->assertJsonPath('error', 'APLICACION_NO_ENCONTRADA');
    }

    public function test_iam_admin_is_not_edited_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->patchJson('/api/iam/v1/aplicaciones/iam-admin', ['descripcion' => 'Otra'])
            ->assertConflict()
            ->assertJsonPath('error', 'CATALOGO_DE_IAM_ADMIN');

        $this->assertSame('Portal de administración de IAM', DB::table('applications')->where('name', 'iam-admin')->value('description'));
        $this->rechazoAuditado('aplicacion.edicion', 'aplicacion', $this->ids['iam-admin']);
    }

    public function test_the_help_desk_gets_403_and_without_a_session_401(): void
    {
        $this->patchJson('/api/iam/v1/aplicaciones/biblioteca', ['descripcion' => 'x'])->assertUnauthorized();

        $this->comoMesaDeAyuda();
        $this->patchJson('/api/iam/v1/aplicaciones/biblioteca', ['descripcion' => 'x'])
            ->assertForbidden()
            ->assertJsonPath('error', 'ACCESO_DENEGADO');

        $this->assertSame('Sistema de biblioteca', DB::table('applications')->where('name', 'biblioteca')->value('description'));
    }
}
