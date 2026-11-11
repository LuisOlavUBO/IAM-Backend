<?php

namespace Tests\Feature\Clientes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec clientes (clientes-revocar): revocar y reactivar un cliente desde el portal, auditado, idempotente y
 * sin revocar los clientes de iam-admin.
 */
class RevocacionDeClientesTest extends TestCase
{
    use IniciaSesionEnIam;
    use RefreshDatabase;

    private const ADMIN = '0199a1b2-c3d4-7e5f-8a9b-000000000001';

    private const HONORARIOS = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a01';

    private const REVOCADO = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a02';

    private const PORTAL = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a03';

    private const SISTEMA = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a04';

    protected function setUp(): void
    {
        parent::setUp();

        $iamAdmin = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
        $honorarios = DB::table('applications')->insertGetId(['name' => 'pago-honorarios']);
        $fuas = DB::table('applications')->insertGetId(['name' => 'fuas']);

        foreach ([
            [self::HONORARIOS, $honorarios, 'pago-honorarios-backend', false],
            [self::REVOCADO, $fuas, 'fuas-backend', true],
            [self::PORTAL, $iamAdmin, 'iam-admin-portal', false],
            [self::SISTEMA, $iamAdmin, 'iam-admin-sistema', true],
        ] as [$id, $aplicacion, $nombre, $revocado]) {
            DB::table('oauth_clients')->insert([
                'id' => $id, 'application_id' => $aplicacion, 'name' => $nombre, 'secret' => 'hash',
                'redirect_uris' => '[]', 'grant_types' => '["client_credentials"]', 'revoked' => $revocado,
                'updated_at' => '2026-01-01 00:00:00',
            ]);
        }
    }

    private function comoAdmin(): void
    {
        UboLogin::actingAs(id: self::ADMIN, roles: ['administrador'], scopes: ['iam:clientes.leer', 'iam:clientes.administrar']);
    }

    private function revocado(string $id): bool
    {
        return (bool) DB::table('oauth_clients')->where('id', $id)->value('revoked');
    }

    // --- Revocación ---

    public function test_revoking_a_client_marks_it_revoked_and_audits_it(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/clientes/'.strtoupper(self::HONORARIOS).'/revocacion')
            ->assertOk()
            ->assertExactJson(['data' => ['revocado' => true]]);

        $this->getJson('/api/iam/v1/clientes/'.self::HONORARIOS)->assertOk()->assertJsonPath('data.revocado', true);
        $this->assertNotSame('2026-01-01 00:00:00.000', DB::table('oauth_clients')->where('id', self::HONORARIOS)->value('updated_at'));
        $this->assertFalse($this->revocado(self::PORTAL));

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('cliente.revocacion', $entrada->accion);
        $this->assertSame('cliente', $entrada->entidad_tipo);
        $this->assertSame(self::HONORARIOS, $entrada->entidad_id);
        $this->assertSame('ok', $entrada->resultado);
        $this->assertSame(self::ADMIN, strtolower((string) $entrada->actor_uuid));
        $this->assertSame(['revocado' => false], json_decode($entrada->antes, true));
        $this->assertSame(['revocado' => true], json_decode($entrada->despues, true));
    }

    public function test_revoking_a_revoked_client_changes_nothing_and_is_not_audited(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/clientes/'.self::REVOCADO.'/revocacion')
            ->assertOk()
            ->assertExactJson(['data' => ['revocado' => true]]);

        $this->assertTrue($this->revocado(self::REVOCADO));
        $this->assertSame('2026-01-01 00:00:00.000', DB::table('oauth_clients')->where('id', self::REVOCADO)->value('updated_at'));
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    // --- Clientes de iam-admin ---

    public function test_a_client_of_iam_admin_is_not_revoked_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/clientes/'.self::PORTAL.'/revocacion')
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'CLIENTE_DE_IAM_ADMIN');

        $this->assertFalse($this->revocado(self::PORTAL));
        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('cliente.revocacion', $entrada->accion);
        $this->assertSame(self::PORTAL, $entrada->entidad_id);
        $this->assertSame('rechazado', $entrada->resultado);
        $this->assertSame('CLIENTE_DE_IAM_ADMIN', $entrada->motivo);
    }

    public function test_a_revoked_client_of_iam_admin_can_be_reactivated(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/clientes/'.self::SISTEMA.'/reactivacion')
            ->assertOk()
            ->assertExactJson(['data' => ['revocado' => false]]);

        $this->assertFalse($this->revocado(self::SISTEMA));
        $this->assertSame('cliente.reactivacion', DB::table('iam_auditoria')->sole()->accion);
    }

    // --- Reactivación ---

    public function test_reactivating_a_client_clears_the_mark_and_audits_it(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/clientes/'.self::REVOCADO.'/reactivacion')
            ->assertOk()
            ->assertExactJson(['data' => ['revocado' => false]]);

        $this->getJson('/api/iam/v1/clientes/'.self::REVOCADO)->assertOk()->assertJsonPath('data.revocado', false);

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('cliente.reactivacion', $entrada->accion);
        $this->assertSame('cliente', $entrada->entidad_tipo);
        $this->assertSame(self::REVOCADO, $entrada->entidad_id);
        $this->assertSame('ok', $entrada->resultado);
        $this->assertSame(['revocado' => true], json_decode($entrada->antes, true));
        $this->assertSame(['revocado' => false], json_decode($entrada->despues, true));
    }

    public function test_reactivating_an_active_client_changes_nothing_and_is_not_audited(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/reactivacion')
            ->assertOk()
            ->assertExactJson(['data' => ['revocado' => false]]);

        $this->assertFalse($this->revocado(self::HONORARIOS));
        $this->assertSame('2026-01-01 00:00:00.000', DB::table('oauth_clients')->where('id', self::HONORARIOS)->value('updated_at'));
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    // --- Errores ---

    public function test_an_invalid_or_unknown_id_answers_404(): void
    {
        $this->comoAdmin();

        foreach (['revocacion', 'reactivacion'] as $accion) {
            foreach (['no-es-uuid', '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4aff'] as $id) {
                $this->postJson("/api/iam/v1/clientes/{$id}/{$accion}")
                    ->assertNotFound()
                    ->assertJsonPath('error', 'CLIENTE_NO_ENCONTRADO');
            }
        }
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_the_help_desk_gets_403(): void
    {
        UboLogin::actingAs(id: self::ADMIN, roles: ['mesa-de-ayuda'], scopes: ['iam:clientes.leer', 'iam:usuarios.administrar']);

        $this->postJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/revocacion')
            ->assertForbidden()
            ->assertJsonPath('error', 'ACCESO_DENEGADO');
        $this->postJson('/api/iam/v1/clientes/'.self::REVOCADO.'/reactivacion')
            ->assertForbidden()
            ->assertJsonPath('error', 'ACCESO_DENEGADO');

        $this->assertFalse($this->revocado(self::HONORARIOS));
        $this->assertTrue($this->revocado(self::REVOCADO));
    }

    public function test_without_a_session_it_answers_401(): void
    {
        $this->postJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/revocacion')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'NO_AUTENTICADO');
        $this->postJson('/api/iam/v1/clientes/'.self::REVOCADO.'/reactivacion')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'NO_AUTENTICADO');

        $this->assertFalse($this->revocado(self::HONORARIOS));
        $this->assertTrue($this->revocado(self::REVOCADO));
    }

    public function test_without_the_csrf_token_it_answers_419(): void
    {
        $this->setUpIam();
        $this->enforceCsrf();
        $this->comoAdmin();

        $this->withBrowserSession()->postJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/revocacion')->assertStatus(419);
        $this->withBrowserSession()->postJson('/api/iam/v1/clientes/'.self::REVOCADO.'/reactivacion')->assertStatus(419);

        $this->assertFalse($this->revocado(self::HONORARIOS));
        $this->assertTrue($this->revocado(self::REVOCADO));
    }
}
