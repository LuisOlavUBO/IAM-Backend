<?php

namespace Tests\Feature\Aplicaciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CatalogoDePrueba;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;

/**
 * Spec aplicaciones (aplicaciones-baja): "Dar de baja y reactivar una aplicación", "Estado en las consultas" e
 * "iam-admin no se da de baja", para una aplicación.
 */
class BajaDeAplicacionTest extends TestCase
{
    use CatalogoDePrueba;
    use IniciaSesionEnIam;
    use RefreshDatabase;

    private const URL = '/api/iam/v1/aplicaciones/geografia';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearCatalogo();
        $this->rol($this->ids['geografia'], 'lector', ['geografia:leer']);
        $this->persona(['geografia/lector']);
        $this->persona(['geografia/lector']);
        $this->cliente('geografia', ['geografia/lector']);
    }

    public function test_without_confirmation_it_answers_the_impact_and_changes_nothing(): void
    {
        $this->comoAdmin();

        $this->postJson(self::URL.'/baja')
            ->assertConflict()
            ->assertJsonPath('error', 'BAJA_SIN_CONFIRMAR')
            ->assertJsonPath('impacto', ['personas' => 2, 'clientes' => 1]);

        $this->assertNull(DB::table('applications')->where('id', $this->ids['geografia'])->value('disabled_at'));
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_confirmed_it_is_disabled_keeps_its_assignments_and_is_audited(): void
    {
        $this->comoAdmin();

        $this->postJson(self::URL.'/baja', ['confirmar_baja' => true])
            ->assertOk()
            ->assertExactJson(['data' => ['activa' => false]]);

        $this->assertNotNull($this->getJson(self::URL)->assertOk()->json('data.baja_desde'));
        $this->assertSame(2, DB::table('role_user')->where('role_id', $this->ids['geografia/lector'])->count());
        $this->assertSame(1, DB::table('oauth_client_role')->where('role_id', $this->ids['geografia/lector'])->count());
        $this->assertSame(['geografia:leer'], $this->permisosDe('geografia/lector'));

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('aplicacion.baja', $entrada->accion);
        $this->assertSame('aplicacion', $entrada->entidad_tipo);
        $this->assertSame((string) $this->ids['geografia'], $entrada->entidad_id);
        $this->assertSame(['activo' => true], json_decode($entrada->antes, true));
        $this->assertSame(['activo' => false], json_decode($entrada->despues, true));
    }

    public function test_disabling_or_reactivating_twice_changes_nothing_the_second_time(): void
    {
        $this->comoAdmin();

        $this->postJson(self::URL.'/baja', ['confirmar_baja' => true])->assertOk();
        $this->postJson(self::URL.'/baja', ['confirmar_baja' => true])->assertOk();
        $this->postJson(self::URL.'/reactivacion')->assertOk()->assertExactJson(['data' => ['activa' => true]]);
        $this->postJson(self::URL.'/reactivacion')->assertOk();

        $this->assertNull($this->getJson(self::URL)->json('data.baja_desde'));
        $this->assertSame(['aplicacion.baja', 'aplicacion.alta'], DB::table('iam_auditoria')->orderBy('id')->pluck('accion')->all());
        $this->assertSame(['activo' => true], json_decode(DB::table('iam_auditoria')->where('accion', 'aplicacion.alta')->value('despues'), true));
    }

    public function test_the_list_shows_since_when_an_application_is_disabled(): void
    {
        $this->comoAdmin();
        DB::table('applications')->where('id', $this->ids['geografia'])->update(['disabled_at' => '2026-11-12 10:00:00']);

        $aplicaciones = collect($this->getJson('/api/iam/v1/aplicaciones')->assertOk()->json('data'))->keyBy('nombre');

        $this->assertStringStartsWith('2026-11-12T10:00:00', $aplicaciones['geografia']['baja_desde']);
        $this->assertNull($aplicaciones['biblioteca']['baja_desde']);
    }

    public function test_iam_admin_is_not_disabled_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/aplicaciones/iam-admin/baja', ['confirmar_baja' => true])
            ->assertConflict()
            ->assertJsonPath('error', 'CATALOGO_DE_IAM_ADMIN');

        $this->assertNull(DB::table('applications')->where('id', $this->ids['iam-admin'])->value('disabled_at'));
        $this->rechazoAuditado('aplicacion.baja', 'aplicacion', $this->ids['iam-admin']);
    }

    public function test_an_unknown_application_answers_404(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/aplicaciones/no-existe/baja', ['confirmar_baja' => true])->assertNotFound();
        $this->postJson('/api/iam/v1/aplicaciones/no-existe/reactivacion')->assertNotFound();
    }

    public function test_the_help_desk_gets_403_without_a_session_401_and_without_csrf_419(): void
    {
        $this->postJson(self::URL.'/baja', ['confirmar_baja' => true])->assertUnauthorized();

        $this->comoMesaDeAyuda();
        $this->postJson(self::URL.'/baja', ['confirmar_baja' => true])->assertForbidden();
        $this->postJson(self::URL.'/reactivacion')->assertForbidden();

        $this->setUpIam();
        $this->enforceCsrf();
        $this->comoAdmin();
        $this->withBrowserSession()->postJson(self::URL.'/baja', ['confirmar_baja' => true])->assertStatus(419);

        $this->assertNull(DB::table('applications')->where('id', $this->ids['geografia'])->value('disabled_at'));
    }
}
