<?php

namespace Tests\Feature\Clientes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec clientes (clientes-rotar-secreto): rotar el secreto de un cliente desde el portal, auditado y sin
 * dejar el secreto en ninguna otra parte.
 */
class RotarSecretoTest extends TestCase
{
    use IniciaSesionEnIam;
    use RefreshDatabase;

    private const ADMIN = '0199a1b2-c3d4-7e5f-8a9b-000000000001';

    private const HONORARIOS = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a01';

    private const PUBLICO = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a02';

    private const PORTAL = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a03';

    private const SISTEMA = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a04';

    private const SECRETO_ANTERIOR = 'secreto-anterior';

    protected function setUp(): void
    {
        parent::setUp();

        $iamAdmin = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
        $honorarios = DB::table('applications')->insertGetId(['name' => 'pago-honorarios']);
        $fuas = DB::table('applications')->insertGetId(['name' => 'fuas']);
        $hash = Hash::make(self::SECRETO_ANTERIOR);

        foreach ([
            [self::HONORARIOS, $honorarios, 'pago-honorarios-backend', $hash],
            [self::PUBLICO, $fuas, 'fuas-spa', null],
            [self::PORTAL, $iamAdmin, 'iam-admin-portal', $hash],
            [self::SISTEMA, $iamAdmin, 'iam-admin-sistema', $hash],
        ] as [$id, $aplicacion, $nombre, $secreto]) {
            DB::table('oauth_clients')->insert([
                'id' => $id, 'application_id' => $aplicacion, 'name' => $nombre, 'secret' => $secreto,
                'redirect_uris' => '[]', 'grant_types' => '["client_credentials"]', 'revoked' => false,
                'updated_at' => '2026-01-01 00:00:00',
            ]);
        }
    }

    private function comoAdmin(): void
    {
        UboLogin::actingAs(id: self::ADMIN, roles: ['administrador'], scopes: ['iam:clientes.leer', 'iam:clientes.administrar']);
    }

    private function secret(string $id): ?string
    {
        return DB::table('oauth_clients')->where('id', $id)->value('secret');
    }

    private function sigueIgual(string $id): void
    {
        $this->assertTrue(Hash::check(self::SECRETO_ANTERIOR, (string) $this->secret($id)));
    }

    // --- Rotación ---

    public function test_rotating_gives_a_new_secret_once_and_the_previous_one_stops_validating(): void
    {
        $this->comoAdmin();

        $respuesta = $this->postJson('/api/iam/v1/clientes/'.strtoupper(self::HONORARIOS).'/secreto')
            ->assertOk()
            ->assertJsonPath('data.variable_env', null)
            ->assertJsonStructure(['data' => ['secreto', 'variable_env']]);

        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
        $secreto = $respuesta->json('data.secreto');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $secreto);
        $hash = (string) $this->secret(self::HONORARIOS);
        $this->assertTrue(Hash::check($secreto, $hash));
        $this->assertFalse(Hash::check(self::SECRETO_ANTERIOR, $hash));
        $this->assertNotSame('2026-01-01 00:00:00.000', DB::table('oauth_clients')->where('id', self::HONORARIOS)->value('updated_at'));
        $this->sigueIgual(self::PORTAL);

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('cliente.secreto.rotacion', $entrada->accion);
        $this->assertSame('cliente', $entrada->entidad_tipo);
        $this->assertSame(self::HONORARIOS, $entrada->entidad_id);
        $this->assertSame('ok', $entrada->resultado);
        $this->assertSame('persona', $entrada->actor_tipo);
        $this->assertSame(self::ADMIN, strtolower((string) $entrada->actor_uuid));
        $this->assertNull($entrada->antes);
        $this->assertNull($entrada->despues);
        $filas = json_encode(DB::table('iam_auditoria')->get()->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString($secreto, $filas);
        $this->assertStringNotContainsString($hash, $filas);

        $detalle = $this->getJson('/api/iam/v1/clientes/'.self::HONORARIOS)->assertOk();
        $this->assertStringNotContainsString($secreto, (string) $detalle->getContent());
        $this->assertStringNotContainsString($hash, (string) $detalle->getContent());
    }

    // --- Clientes de iam-admin ---

    public function test_a_client_of_iam_admin_requires_its_name_in_confirmar(): void
    {
        $this->comoAdmin();

        foreach ([[], ['confirmar' => 'iam-admin-sistema'], ['confirmar' => 'IAM-ADMIN-PORTAL']] as $cuerpo) {
            $this->postJson('/api/iam/v1/clientes/'.self::PORTAL.'/secreto', $cuerpo)
                ->assertUnprocessable()
                ->assertHeader('Content-Type', 'application/problem+json')
                ->assertJsonPath('error', 'VALIDACION')
                ->assertJsonStructure(['errores' => ['confirmar']]);
        }

        $this->sigueIgual(self::PORTAL);
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_confirming_a_client_of_iam_admin_says_which_variable_to_update(): void
    {
        $this->comoAdmin();

        foreach ([self::PORTAL => ['iam-admin-portal', 'UBO_LOGIN_CLIENT_SECRET'], self::SISTEMA => ['iam-admin-sistema', 'UBO_AUTH_CLIENT_SECRET']] as $id => [$nombre, $variable]) {
            $respuesta = $this->postJson("/api/iam/v1/clientes/{$id}/secreto", ['confirmar' => $nombre])
                ->assertOk()
                ->assertJsonPath('data.variable_env', $variable);

            $this->assertTrue(Hash::check($respuesta->json('data.secreto'), (string) $this->secret($id)));
        }
        $this->assertSame(2, DB::table('iam_auditoria')->where('accion', 'cliente.secreto.rotacion')->count());
    }

    public function test_confirmar_is_ignored_for_other_clients(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/secreto', ['confirmar' => 'otra cosa'])->assertOk();
    }

    // --- Errores ---

    public function test_a_public_client_does_not_get_a_secret(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/iam/v1/clientes/'.self::PUBLICO.'/secreto')
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'CLIENTE_PUBLICO');

        $this->assertNull($this->secret(self::PUBLICO));
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_an_invalid_or_unknown_id_answers_404(): void
    {
        $this->comoAdmin();

        foreach (['no-es-uuid', '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4aff'] as $id) {
            $this->postJson("/api/iam/v1/clientes/{$id}/secreto")
                ->assertNotFound()
                ->assertJsonPath('error', 'CLIENTE_NO_ENCONTRADO');
        }
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_the_help_desk_gets_403(): void
    {
        UboLogin::actingAs(id: self::ADMIN, roles: ['mesa-de-ayuda'], scopes: ['iam:clientes.leer', 'iam:usuarios.administrar']);

        $this->postJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/secreto')
            ->assertForbidden()
            ->assertJsonPath('error', 'ACCESO_DENEGADO');

        $this->sigueIgual(self::HONORARIOS);
    }

    public function test_without_a_session_it_answers_401(): void
    {
        $this->postJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/secreto')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'NO_AUTENTICADO');

        $this->sigueIgual(self::HONORARIOS);
    }

    public function test_without_the_csrf_token_it_answers_419(): void
    {
        $this->setUpIam();
        $this->enforceCsrf();
        $this->comoAdmin();

        $this->withBrowserSession()->postJson('/api/iam/v1/clientes/'.self::HONORARIOS.'/secreto')->assertStatus(419);

        $this->sigueIgual(self::HONORARIOS);
    }
}
