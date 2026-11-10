<?php

namespace Tests\Feature\Clientes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec clientes: detalle de un cliente, y sin secretos, ids internos ni escritura.
 */
class VerClienteTest extends TestCase
{
    use RefreshDatabase;

    private const SISTEMA = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';

    private const PORTAL = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5c';

    private string $hash = '';

    private function conPermiso(): void
    {
        UboLogin::actingAs(id: 'a', roles: ['administrador'], scopes: ['iam:clientes.leer']);
    }

    /**
     * The two clients of iam-admin as iam:install leaves them: the system one with roles of api-auth
     * (inserted in an order different from the expected one) and the portal one with a redirection.
     */
    private function clientesDeInstalacion(): void
    {
        $iamAdmin = DB::table('applications')->insertGetId(['name' => 'iam-admin']);
        $apiAuth = DB::table('applications')->insertGetId(['name' => 'api-auth']);
        $this->hash = Hash::make('secreto-de-prueba');

        DB::table('oauth_clients')->insert([
            'id' => self::SISTEMA, 'application_id' => $iamAdmin, 'name' => 'iam-admin-sistema', 'secret' => $this->hash,
            'provider' => null, 'redirect_uris' => '[]', 'grant_types' => '["client_credentials"]', 'revoked' => false,
            'created_at' => '2026-11-09 10:00:00', 'updated_at' => '2026-11-10 12:30:00',
        ]);
        DB::table('oauth_clients')->insert([
            'id' => self::PORTAL, 'application_id' => $iamAdmin, 'name' => 'iam-admin-portal', 'secret' => $this->hash,
            'provider' => 'users', 'redirect_uris' => '["http://localhost:4200/api/iam/v1/auth/callback"]',
            'grant_types' => '["authorization_code","refresh_token"]', 'revoked' => false,
        ]);

        foreach (['session-revoker' => 'Cierra sesiones', 'introspector' => null] as $rol => $descripcion) {
            $id = DB::table('roles')->insertGetId(['application_id' => $apiAuth, 'name' => $rol, 'description' => $descripcion]);
            DB::table('oauth_client_role')->insert(['client_id' => self::SISTEMA, 'role_id' => $id]);
        }
    }

    public function test_a_client_credentials_client_with_roles_of_another_application(): void
    {
        $this->clientesDeInstalacion();
        $this->conPermiso();

        $respuesta = $this->getJson('/api/iam/v1/clientes/'.self::SISTEMA)
            ->assertOk()
            ->assertJsonPath('data.id', self::SISTEMA)
            ->assertJsonPath('data.nombre', 'iam-admin-sistema')
            ->assertJsonPath('data.aplicacion', 'iam-admin')
            ->assertJsonPath('data.concesiones', ['client_credentials'])
            ->assertJsonPath('data.confidencial', true)
            ->assertJsonPath('data.redirecciones', [])
            ->assertJsonPath('data.revocado', false)
            ->assertJsonPath('data.roles', [
                ['aplicacion' => 'api-auth', 'nombre' => 'introspector', 'descripcion' => null],
                ['aplicacion' => 'api-auth', 'nombre' => 'session-revoker', 'descripcion' => 'Cierra sesiones'],
            ]);

        $this->assertStringStartsWith('2026-11-09T10:00:00', $respuesta->json('data.creado'));
        $this->assertStringStartsWith('2026-11-10T12:30:00', $respuesta->json('data.actualizado'));
    }

    public function test_an_authorization_code_client_with_its_redirection(): void
    {
        $this->clientesDeInstalacion();
        $this->conPermiso();

        $this->getJson('/api/iam/v1/clientes/'.self::PORTAL)
            ->assertOk()
            ->assertJsonPath('data.concesiones', ['authorization_code', 'refresh_token'])
            ->assertJsonPath('data.redirecciones', ['http://localhost:4200/api/iam/v1/auth/callback'])
            ->assertJsonPath('data.roles', [])
            ->assertJsonPath('data.creado', null);
    }

    public function test_a_public_client_is_not_confidential_and_bad_json_is_an_empty_list(): void
    {
        $aplicacion = DB::table('applications')->insertGetId(['name' => 'fuas']);
        DB::table('oauth_clients')->insert([
            'id' => self::PORTAL, 'application_id' => $aplicacion, 'name' => 'publico', 'secret' => null,
            'redirect_uris' => 'no es json', 'grant_types' => '{"a":"b"}', 'revoked' => true,
        ]);
        $this->conPermiso();

        $this->getJson('/api/iam/v1/clientes/'.self::PORTAL)
            ->assertOk()
            ->assertJsonPath('data.confidencial', false)
            ->assertJsonPath('data.revocado', true)
            ->assertJsonPath('data.redirecciones', [])
            ->assertJsonPath('data.concesiones', []);
    }

    public function test_the_id_in_upper_case_finds_the_client(): void
    {
        $this->clientesDeInstalacion();
        $this->conPermiso();

        $this->getJson('/api/iam/v1/clientes/'.strtoupper(self::SISTEMA))
            ->assertOk()
            ->assertJsonPath('data.id', self::SISTEMA);
    }

    public function test_an_invalid_or_unknown_id_answers_404(): void
    {
        $this->conPermiso();

        foreach (['no-es-uuid', self::SISTEMA] as $id) {
            $this->getJson("/api/iam/v1/clientes/{$id}")
                ->assertNotFound()
                ->assertHeader('Content-Type', 'application/problem+json')
                ->assertJsonPath('error', 'CLIENTE_NO_ENCONTRADO');
        }
    }

    public function test_a_person_without_the_permission_gets_403(): void
    {
        $this->clientesDeInstalacion();
        UboLogin::actingAs(id: 'a', roles: ['mesa-de-ayuda'], scopes: ['iam:aplicaciones.leer']);

        $this->getJson('/api/iam/v1/clientes/'.self::SISTEMA)
            ->assertForbidden()
            ->assertJsonPath('error', 'ACCESO_DENEGADO');
    }

    public function test_responses_have_no_secret_nor_internal_ids_and_nothing_is_written(): void
    {
        $this->clientesDeInstalacion();
        $this->conPermiso();
        $tablas = ['oauth_clients', 'oauth_client_role', 'iam_auditoria'];
        $antes = array_map(fn (string $tabla) => DB::table($tabla)->count(), $tablas);

        $listado = $this->getJson('/api/iam/v1/clientes')->assertOk();
        $detalle = $this->getJson('/api/iam/v1/clientes/'.self::SISTEMA)->assertOk();

        foreach ([$listado, $detalle] as $respuesta) {
            $json = $respuesta->json();
            $claves = [];
            array_walk_recursive($json, function (mixed $valor, string|int $clave) use (&$claves) {
                $claves[] = $clave;
            });
            foreach (['secret', 'secreto', 'application_id', 'role_id', 'owner_id', 'owner_type', 'provider'] as $prohibida) {
                $this->assertNotContains($prohibida, $claves);
            }
            $this->assertStringNotContainsString($this->hash, $respuesta->getContent());
        }
        $this->assertSame($antes, array_map(fn (string $tabla) => DB::table($tabla)->count(), $tablas));
    }
}
