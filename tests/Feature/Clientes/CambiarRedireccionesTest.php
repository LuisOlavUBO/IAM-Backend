<?php

namespace Tests\Feature\Clientes;

use App\Domains\Clientes\Domain\Redireccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec clientes (clientes-roles-y-redirecciones): cambiar las redirecciones de un cliente de login, auditado,
 * idempotente, solo en clientes authorization-code y sin tocar los clientes de iam-admin.
 */
class CambiarRedireccionesTest extends TestCase
{
    use IniciaSesionEnIam;
    use RefreshDatabase;

    private const ADMIN = '0199a1b2-c3d4-7e5f-8a9b-000000000001';

    private const SATELITE = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a01';

    private const HONORARIOS = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a02';

    private const PORTAL = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a03';

    private const PROD = 'https://satelite.ubo.cl/auth/callback';

    private const QA = 'https://satelite-qa.ubo.cl/auth/callback';

    protected function setUp(): void
    {
        parent::setUp();

        $iamAdmin = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
        $honorarios = DB::table('applications')->insertGetId(['name' => 'pago-honorarios']);

        foreach ([
            [self::SATELITE, $honorarios, 'satelite-web', '["authorization_code","refresh_token"]', json_encode([self::PROD])],
            [self::HONORARIOS, $honorarios, 'pago-honorarios-backend', '["client_credentials"]', '[]'],
            [self::PORTAL, $iamAdmin, 'iam-admin-portal', '["authorization_code","refresh_token"]', '["http://localhost:4200/callback"]'],
        ] as [$id, $aplicacion, $nombre, $concesiones, $redirecciones]) {
            DB::table('oauth_clients')->insert([
                'id' => $id, 'application_id' => $aplicacion, 'name' => $nombre, 'secret' => 'hash',
                'redirect_uris' => $redirecciones, 'grant_types' => $concesiones, 'revoked' => false,
                'updated_at' => '2026-01-01 00:00:00',
            ]);
        }
    }

    private function comoAdmin(): void
    {
        UboLogin::actingAs(id: self::ADMIN, roles: ['administrador'], scopes: ['iam:clientes.leer', 'iam:clientes.administrar']);
    }

    /** @return list<string> */
    private function redirecciones(string $id): array
    {
        return json_decode((string) DB::table('oauth_clients')->where('id', $id)->value('redirect_uris'), true);
    }

    private function rechazoAuditado(string $cliente, string $motivo): void
    {
        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('cliente.redirecciones.cambio', $entrada->accion);
        $this->assertSame($cliente, $entrada->entidad_id);
        $this->assertSame('rechazado', $entrada->resultado);
        $this->assertSame($motivo, $entrada->motivo);
    }

    public function test_adding_an_environment_replaces_the_list_and_audits_it(): void
    {
        $this->comoAdmin();

        $this->putJson('/api/iam/v1/clientes/'.strtoupper(self::SATELITE).'/redirecciones', ['redirecciones' => [self::PROD, self::QA, self::QA]])
            ->assertOk()
            ->assertExactJson(['data' => ['redirecciones' => [self::PROD, self::QA]]]);

        $this->getJson('/api/iam/v1/clientes/'.self::SATELITE)->assertOk()->assertJsonPath('data.redirecciones', [self::PROD, self::QA]);
        $this->assertNotSame('2026-01-01 00:00:00.000', DB::table('oauth_clients')->where('id', self::SATELITE)->value('updated_at'));

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('cliente.redirecciones.cambio', $entrada->accion);
        $this->assertSame('cliente', $entrada->entidad_tipo);
        $this->assertSame(self::SATELITE, $entrada->entidad_id);
        $this->assertSame('ok', $entrada->resultado);
        $this->assertSame(['redirecciones' => [self::PROD]], json_decode($entrada->antes, true));
        $this->assertSame(['redirecciones' => [self::PROD, self::QA]], json_decode($entrada->despues, true));
    }

    public function test_the_same_redirections_in_another_order_change_nothing(): void
    {
        DB::table('oauth_clients')->where('id', self::SATELITE)->update(['redirect_uris' => json_encode([self::PROD, self::QA])]);
        $this->comoAdmin();

        $this->putJson('/api/iam/v1/clientes/'.self::SATELITE.'/redirecciones', ['redirecciones' => [self::QA, self::PROD]])
            ->assertOk()
            ->assertExactJson(['data' => ['redirecciones' => [self::PROD, self::QA]]]);

        $this->assertSame([self::PROD, self::QA], $this->redirecciones(self::SATELITE));
        $this->assertSame('2026-01-01 00:00:00.000', DB::table('oauth_clients')->where('id', self::SATELITE)->value('updated_at'));
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_empty_redirections_answer_422(): void
    {
        $this->comoAdmin();

        foreach ([['redirecciones' => []], []] as $cuerpo) {
            $this->putJson('/api/iam/v1/clientes/'.self::SATELITE.'/redirecciones', $cuerpo)
                ->assertUnprocessable()
                ->assertJsonPath('error', 'VALIDACION')
                ->assertJsonStructure(['errores' => ['redirecciones']]);
        }

        $this->assertSame([self::PROD], $this->redirecciones(self::SATELITE));
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_an_unsafe_redirection_answers_422(): void
    {
        $this->comoAdmin();

        foreach (['http://satelite.ubo.cl/cb', 'https://satelite.ubo.cl/cb#x', '/cb'] as $insegura) {
            $respuesta = $this->putJson('/api/iam/v1/clientes/'.self::SATELITE.'/redirecciones', ['redirecciones' => [self::PROD, $insegura]])
                ->assertUnprocessable();

            $this->assertSame([Redireccion::MENSAJE], $respuesta->json('errores')['redirecciones.1'] ?? null);
        }

        $this->assertSame([self::PROD], $this->redirecciones(self::SATELITE));
    }

    public function test_a_service_client_has_no_redirections_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->putJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/redirecciones', ['redirecciones' => [self::PROD]])
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'CLIENTE_SIN_REDIRECCIONES');

        $this->assertSame([], $this->redirecciones(self::HONORARIOS));
        $this->rechazoAuditado(self::HONORARIOS, 'CLIENTE_SIN_REDIRECCIONES');
    }

    public function test_a_client_of_iam_admin_keeps_its_redirections_and_the_attempt_is_audited(): void
    {
        $this->comoAdmin();

        $this->putJson('/api/iam/v1/clientes/'.self::PORTAL.'/redirecciones', ['redirecciones' => [self::PROD]])
            ->assertConflict()
            ->assertJsonPath('error', 'CLIENTE_DE_IAM_ADMIN');

        $this->assertSame(['http://localhost:4200/callback'], $this->redirecciones(self::PORTAL));
        $this->rechazoAuditado(self::PORTAL, 'CLIENTE_DE_IAM_ADMIN');
    }

    public function test_an_invalid_or_unknown_client_answers_404(): void
    {
        $this->comoAdmin();

        foreach (['no-es-uuid', '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4aff'] as $id) {
            $this->putJson("/api/iam/v1/clientes/{$id}/redirecciones", ['redirecciones' => [self::PROD]])
                ->assertNotFound()
                ->assertJsonPath('error', 'CLIENTE_NO_ENCONTRADO');
        }
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_the_help_desk_gets_403(): void
    {
        UboLogin::actingAs(id: self::ADMIN, roles: ['mesa-de-ayuda'], scopes: ['iam:clientes.leer', 'iam:usuarios.administrar']);

        $this->putJson('/api/iam/v1/clientes/'.self::SATELITE.'/redirecciones', ['redirecciones' => [self::QA]])
            ->assertForbidden()
            ->assertJsonPath('error', 'ACCESO_DENEGADO');

        $this->assertSame([self::PROD], $this->redirecciones(self::SATELITE));
    }

    public function test_without_a_session_it_answers_401(): void
    {
        $this->putJson('/api/iam/v1/clientes/'.self::SATELITE.'/redirecciones', ['redirecciones' => [self::QA]])
            ->assertUnauthorized()
            ->assertJsonPath('error', 'NO_AUTENTICADO');

        $this->assertSame([self::PROD], $this->redirecciones(self::SATELITE));
    }

    public function test_without_the_csrf_token_it_answers_419(): void
    {
        $this->setUpIam();
        $this->enforceCsrf();
        $this->comoAdmin();

        $this->withBrowserSession()->putJson('/api/iam/v1/clientes/'.self::SATELITE.'/redirecciones', ['redirecciones' => [self::QA]])->assertStatus(419);

        $this->assertSame([self::PROD], $this->redirecciones(self::SATELITE));
    }
}
