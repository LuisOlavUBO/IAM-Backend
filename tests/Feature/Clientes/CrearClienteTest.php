<?php

namespace Tests\Feature\Clientes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\IniciaSesionEnIam;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec clientes (clientes-crear): crear clientes confidenciales desde el portal, con el secreto mostrado una
 * sola vez, el nombre único y la creación auditada sin el secreto.
 */
class CrearClienteTest extends TestCase
{
    use IniciaSesionEnIam;
    use RefreshDatabase;

    private const ADMIN = '0199a1b2-c3d4-7e5f-8a9b-000000000001';

    private const EXISTENTE = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a01';

    private const REDIRECCION = 'https://satelite.ubo.cl/auth/callback';

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('applications')->insert([['name' => 'iam-admin'], ['name' => 'satelite']]);
        DB::table('oauth_clients')->insert([
            'id' => self::EXISTENTE, 'application_id' => DB::table('applications')->where('name', 'satelite')->value('id'),
            'name' => 'satelite-web', 'secret' => 'hash', 'redirect_uris' => '[]', 'grant_types' => '["client_credentials"]', 'revoked' => false,
        ]);
    }

    private function comoAdmin(): void
    {
        UboLogin::actingAs(id: self::ADMIN, roles: ['administrador'], scopes: ['iam:clientes.leer', 'iam:clientes.administrar']);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function crear(array $cambios = []): TestResponse
    {
        return $this->postJson('/api/iam/v1/clientes', [
            'nombre' => 'satelite-backend', 'aplicacion' => 'satelite', 'tipo' => 'authorization-code', 'redirecciones' => [self::REDIRECCION],
            ...$cambios,
        ]);
    }

    private function nadaCreado(): void
    {
        $this->assertSame(1, DB::table('oauth_clients')->count());
    }

    // --- Creación ---

    public function test_an_authorization_code_client_for_the_login_of_a_system(): void
    {
        $this->comoAdmin();

        $respuesta = $this->crear()->assertCreated()->assertJsonStructure(['data' => ['id', 'secreto']]);

        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
        $id = $respuesta->json('data.id');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id);
        $secreto = $respuesta->json('data.secreto');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $secreto);

        $fila = DB::table('oauth_clients')->where('id', $id)->sole();
        $this->assertSame('users', $fila->provider);
        $this->assertTrue(Hash::check($secreto, $fila->secret));

        $this->getJson('/api/iam/v1/clientes/'.$id)
            ->assertOk()
            ->assertJsonPath('data.nombre', 'satelite-backend')
            ->assertJsonPath('data.aplicacion', 'satelite')
            ->assertJsonPath('data.concesiones', ['authorization_code', 'refresh_token'])
            ->assertJsonPath('data.redirecciones', [self::REDIRECCION])
            ->assertJsonPath('data.confidencial', true)
            ->assertJsonPath('data.revocado', false)
            ->assertJsonPath('data.roles', []);
    }

    public function test_a_service_client_without_redirects(): void
    {
        $this->comoAdmin();

        $id = $this->crear(['nombre' => 'satelite-worker', 'tipo' => 'client-credentials', 'redirecciones' => null])
            ->assertCreated()
            ->json('data.id');

        $fila = DB::table('oauth_clients')->where('id', $id)->sole();
        $this->assertNull($fila->provider);
        $this->assertSame(['client_credentials'], json_decode($fila->grant_types, true));
        $this->assertSame([], json_decode($fila->redirect_uris, true));
        $this->getJson('/api/iam/v1/clientes/'.$id)->assertOk()->assertJsonPath('data.confidencial', true);
    }

    public function test_the_creation_is_audited_without_the_secret_nor_its_hash(): void
    {
        $this->comoAdmin();

        $respuesta = $this->crear(['aplicacion' => 'SATELITE'])->assertCreated();
        $id = $respuesta->json('data.id');
        $hash = (string) DB::table('oauth_clients')->where('id', $id)->value('secret');

        $entrada = DB::table('iam_auditoria')->sole();
        $this->assertSame('cliente.creacion', $entrada->accion);
        $this->assertSame('cliente', $entrada->entidad_tipo);
        $this->assertSame($id, $entrada->entidad_id);
        $this->assertSame('ok', $entrada->resultado);
        $this->assertSame(self::ADMIN, strtolower((string) $entrada->actor_uuid));
        $this->assertNull($entrada->antes);
        $this->assertSame(
            ['nombre' => 'satelite-backend', 'aplicacion' => 'satelite', 'tipo' => 'authorization-code', 'redirecciones' => [self::REDIRECCION]],
            json_decode($entrada->despues, true),
        );
        $filas = json_encode(DB::table('iam_auditoria')->get()->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString($respuesta->json('data.secreto'), $filas);
        $this->assertStringNotContainsString($hash, $filas);

        $detalle = (string) $this->getJson('/api/iam/v1/clientes/'.$id)->assertOk()->getContent();
        $this->assertStringNotContainsString($respuesta->json('data.secreto'), $detalle);
        $this->assertStringNotContainsString($hash, $detalle);
    }

    // --- Validación ---

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function datosInvalidos(): array
    {
        return [
            'sin nombre' => [['nombre' => '  '], 'nombre'],
            'nombre largo' => [['nombre' => str_repeat('a', 101)], 'nombre'],
            'sin aplicación' => [['aplicacion' => null], 'aplicacion'],
            'aplicación inexistente' => [['aplicacion' => 'no-existe'], 'aplicacion'],
            'aplicación iam-admin' => [['aplicacion' => 'iam-admin'], 'aplicacion'],
            'tipo desconocido' => [['tipo' => 'password'], 'tipo'],
            'authorization-code sin redirecciones' => [['redirecciones' => []], 'redirecciones'],
            'client-credentials con redirecciones' => [['tipo' => 'client-credentials'], 'redirecciones'],
            'redirección http fuera de localhost' => [['redirecciones' => [self::REDIRECCION, 'http://satelite.ubo.cl/callback']], 'redirecciones.1'],
            'redirección relativa' => [['redirecciones' => ['/callback']], 'redirecciones.0'],
            'redirección con fragmento' => [['redirecciones' => [self::REDIRECCION.'#x']], 'redirecciones.0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    #[DataProvider('datosInvalidos')]
    public function test_invalid_data_answers_422_in_its_field_and_creates_nothing(array $cambios, string $campo): void
    {
        $this->comoAdmin();

        $this->crear($cambios)
            ->assertUnprocessable()
            ->assertJsonPath('error', 'VALIDACION')
            ->assertJsonStructure(['errores' => [$campo]]);

        $this->nadaCreado();
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_redirects_on_localhost_are_accepted(): void
    {
        $this->comoAdmin();

        $this->crear(['redirecciones' => ['http://localhost:4200/api/iam/v1/auth/callback']])->assertCreated();
    }

    // --- Nombre único ---

    public function test_a_repeated_name_without_distinguishing_case_answers_409(): void
    {
        $this->comoAdmin();

        $this->crear(['nombre' => 'Satelite-Web'])
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('error', 'CLIENTE_EXISTENTE');

        $this->nadaCreado();
        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    // --- Acceso ---

    public function test_the_help_desk_gets_403(): void
    {
        UboLogin::actingAs(id: self::ADMIN, roles: ['mesa-de-ayuda'], scopes: ['iam:clientes.leer', 'iam:usuarios.administrar']);

        $this->crear()->assertForbidden()->assertJsonPath('error', 'ACCESO_DENEGADO');

        $this->nadaCreado();
    }

    public function test_without_a_session_it_answers_401(): void
    {
        $this->crear()->assertUnauthorized()->assertJsonPath('error', 'NO_AUTENTICADO');

        $this->nadaCreado();
    }

    public function test_without_the_csrf_token_it_answers_419(): void
    {
        $this->setUpIam();
        $this->enforceCsrf();
        $this->comoAdmin();

        $this->withBrowserSession()->postJson('/api/iam/v1/clientes', [
            'nombre' => 'satelite-backend', 'aplicacion' => 'satelite', 'tipo' => 'client-credentials',
        ])->assertStatus(419);

        $this->nadaCreado();
    }
}
