<?php

namespace Tests\Feature\Aplicaciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CatalogoDePrueba;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;

/**
 * Spec aplicaciones (aplicaciones-baja): "Dar de baja y reactivar un rol", "Estado en las consultas" e
 * "iam-admin no se da de baja", para un rol.
 */
class BajaDeRolTest extends TestCase
{
    use CatalogoDePrueba;
    use IniciaSesionEnIam;
    use RefreshDatabase;

    private const URL = '/api/iam/v1/aplicaciones/geografia/roles/lector';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearCatalogo();
        $this->rol($this->ids['geografia'], 'lector', ['geografia:leer']);
        $this->persona(['geografia/lector']);
        $this->persona(['geografia/lector']);
    }

    public function test_without_confirmation_it_answers_the_impact_and_changes_nothing(): void
    {
        $this->comoAdmin();

        $this->postJson(self::URL.'/baja')
            ->assertConflict()
            ->assertJsonPath('error', 'BAJA_SIN_CONFIRMAR')
            ->assertJsonPath('impacto', ['personas' => 2, 'clientes' => 0, 'permisos' => ['geografia:leer']]);

        $this->assertNull(DB::table('roles')->where('id', $this->ids['geografia/lector'])->value('disabled_at'));
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_confirmed_it_is_disabled_keeps_who_has_it_and_is_audited(): void
    {
        $this->comoAdmin();

        $this->postJson(self::URL.'/baja', ['confirmar_baja' => true])
            ->assertOk()
            ->assertExactJson(['data' => ['activo' => false]]);

        $roles = collect($this->getJson('/api/iam/v1/aplicaciones/geografia')->assertOk()->json('data.roles'))->keyBy('nombre');
        $this->assertNotNull($roles['lector']['baja_desde']);
        $this->assertSame(2, DB::table('role_user')->where('role_id', $this->ids['geografia/lector'])->count());
        $this->assertSame(['geografia:leer'], $this->permisosDe('geografia/lector'));

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('rol.baja', $entrada->accion);
        $this->assertSame('rol', $entrada->entidad_tipo);
        $this->assertSame((string) $this->ids['geografia/lector'], $entrada->entidad_id);
        $this->assertSame(['activo' => false], json_decode($entrada->despues, true));
    }

    public function test_disabling_or_reactivating_twice_changes_nothing_the_second_time(): void
    {
        $this->comoAdmin();

        $this->postJson(self::URL.'/baja', ['confirmar_baja' => true])->assertOk();
        $this->postJson(self::URL.'/baja', ['confirmar_baja' => true])->assertOk();
        $this->postJson(self::URL.'/reactivacion')->assertOk()->assertExactJson(['data' => ['activo' => true]]);
        $this->postJson(self::URL.'/reactivacion')->assertOk();

        $this->assertNull(DB::table('roles')->where('id', $this->ids['geografia/lector'])->value('disabled_at'));
        $this->assertSame(['rol.baja', 'rol.alta'], DB::table('iam_auditoria')->orderBy('id')->pluck('accion')->all());
    }

    public function test_a_role_of_iam_admin_is_not_disabled_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/aplicaciones/iam-admin/roles/mesa-de-ayuda/baja', ['confirmar_baja' => true])
            ->assertConflict()
            ->assertJsonPath('error', 'CATALOGO_DE_IAM_ADMIN');

        $this->assertNull(DB::table('roles')->where('id', $this->ids['iam-admin/mesa-de-ayuda'])->value('disabled_at'));
        $this->rechazoAuditado('rol.baja', 'rol', $this->ids['iam-admin/mesa-de-ayuda']);
    }

    public function test_an_unknown_role_answers_404(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/aplicaciones/geografia/roles/no-existe/baja', ['confirmar_baja' => true])
            ->assertNotFound()
            ->assertJsonPath('error', 'ROL_NO_ENCONTRADO');
        $this->postJson('/api/iam/v1/aplicaciones/geografia/roles/no-existe/reactivacion')->assertNotFound();
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

        $this->assertNull(DB::table('roles')->where('id', $this->ids['geografia/lector'])->value('disabled_at'));
    }
}
