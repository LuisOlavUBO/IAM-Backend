<?php

namespace Tests\Feature\Clientes;

use App\Domains\Clientes\Domain\Dto\ClienteParaEscribirDto;
use App\Domains\Clientes\Domain\Dto\RolDeAplicacionDto;
use App\Domains\Clientes\Domain\Interfaces\IClientesRepository;
use App\Domains\Clientes\Infrastructure\ClientesRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Design D2 of clientes-rotar-secreto: the write repository of OAuth clients.
 */
class ClientesRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIDENCIAL = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a01';

    private const PUBLICO = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a02';

    private const OTRO = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a03';

    private IClientesRepository $clientes;

    protected function setUp(): void
    {
        parent::setUp();

        $fuas = DB::table('applications')->insertGetId(['name' => 'fuas']);
        $honorarios = DB::table('applications')->insertGetId(['name' => 'pago-honorarios']);
        DB::table('oauth_clients')->insert([
            ['id' => self::CONFIDENCIAL, 'application_id' => $honorarios, 'name' => 'backend', 'secret' => 'hash-anterior', 'redirect_uris' => '[]', 'grant_types' => '["client_credentials"]', 'revoked' => false, 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => self::PUBLICO, 'application_id' => $fuas, 'name' => 'fuas-spa', 'secret' => null, 'redirect_uris' => '["https://fuas.ubo.cl/cb"]', 'grant_types' => '["authorization_code","refresh_token"]', 'revoked' => false, 'updated_at' => null],
            ['id' => self::OTRO, 'application_id' => $fuas, 'name' => 'backend', 'secret' => 'otro-hash', 'redirect_uris' => '[]', 'grant_types' => '[]', 'revoked' => true, 'updated_at' => null],
        ]);

        $this->clientes = app(IClientesRepository::class);
    }

    public function test_the_port_is_bound_to_its_sql_adapter(): void
    {
        $this->assertInstanceOf(ClientesRepository::class, $this->clientes);
    }

    public function test_it_finds_a_client_by_id_in_any_case(): void
    {
        $this->assertEquals(
            new ClienteParaEscribirDto(self::CONFIDENCIAL, 'backend', 'pago-honorarios', true, false, 'client-credentials', []),
            $this->clientes->buscar(strtoupper(self::CONFIDENCIAL)),
        );
        $this->assertEquals(
            new ClienteParaEscribirDto(self::PUBLICO, 'fuas-spa', 'fuas', false, false, 'authorization-code', ['https://fuas.ubo.cl/cb']),
            $this->clientes->buscar(self::PUBLICO),
        );
        $this->assertNull($this->clientes->buscar('0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4aff'));
    }

    public function test_it_finds_every_client_with_a_name_ordered_by_application(): void
    {
        $this->assertEquals([
            new ClienteParaEscribirDto(self::OTRO, 'backend', 'fuas', true, true, null, []),
            new ClienteParaEscribirDto(self::CONFIDENCIAL, 'backend', 'pago-honorarios', true, false, 'client-credentials', []),
        ], $this->clientes->buscarPorNombre('backend'));
        $this->assertSame([], $this->clientes->buscarPorNombre('no-existe'));
    }

    public function test_changing_the_secret_touches_only_that_client(): void
    {
        $this->freezeSecond();

        $this->clientes->cambiarSecreto(self::CONFIDENCIAL, 'hash-nuevo');

        $fila = DB::table('oauth_clients')->where('id', self::CONFIDENCIAL)->first(['secret', 'updated_at']);
        $this->assertSame('hash-nuevo', $fila->secret);
        $this->assertStringStartsWith(now()->format('Y-m-d H:i:s'), $fila->updated_at);
        $this->assertSame('otro-hash', DB::table('oauth_clients')->where('id', self::OTRO)->value('secret'));
    }

    public function test_marking_revoked_changes_only_that_client_and_says_whether_it_changed(): void
    {
        $this->freezeSecond();

        $this->assertTrue($this->clientes->marcarRevocado(self::CONFIDENCIAL, true));

        $fila = DB::table('oauth_clients')->where('id', self::CONFIDENCIAL)->first(['revoked', 'updated_at']);
        $this->assertTrue((bool) $fila->revoked);
        $this->assertStringStartsWith(now()->format('Y-m-d H:i:s'), $fila->updated_at);
        $this->assertFalse((bool) DB::table('oauth_clients')->where('id', self::PUBLICO)->value('revoked'));

        $this->assertFalse($this->clientes->marcarRevocado(self::CONFIDENCIAL, true));
        $this->assertNull(DB::table('oauth_clients')->where('id', self::OTRO)->value('updated_at'));
        $this->assertTrue($this->clientes->marcarRevocado(self::OTRO, false));
        $this->assertFalse((bool) DB::table('oauth_clients')->where('id', self::OTRO)->value('revoked'));
    }

    // --- clientes-roles-y-redirecciones (design D3) ---

    public function test_it_finds_a_role_of_any_application(): void
    {
        $maestros = DB::table('applications')->insertGetId(['name' => 'api-maestros']);
        $id = DB::table('roles')->insertGetId(['application_id' => $maestros, 'name' => 'maestros-lector']);

        $this->assertEquals(new RolDeAplicacionDto($id, 'api-maestros', 'maestros-lector'), $this->clientes->rol('api-maestros', 'maestros-lector'));
        $this->assertNull($this->clientes->rol('fuas', 'maestros-lector'));
        $this->assertNull($this->clientes->rol('api-maestros', 'no-existe'));
    }

    public function test_it_gives_and_takes_a_role_only_to_that_client(): void
    {
        $maestros = DB::table('applications')->insertGetId(['name' => 'api-maestros']);
        $rol = DB::table('roles')->insertGetId(['application_id' => $maestros, 'name' => 'maestros-lector']);
        DB::table('oauth_client_role')->insert(['client_id' => self::OTRO, 'role_id' => $rol]);

        $this->assertFalse($this->clientes->tieneRol(self::CONFIDENCIAL, $rol));
        $this->clientes->asignarRol(self::CONFIDENCIAL, $rol);
        $this->assertTrue($this->clientes->tieneRol(self::CONFIDENCIAL, $rol));

        $this->clientes->quitarRol(self::CONFIDENCIAL, $rol);
        $this->assertFalse($this->clientes->tieneRol(self::CONFIDENCIAL, $rol));
        $this->assertTrue($this->clientes->tieneRol(self::OTRO, $rol));
    }

    public function test_changing_the_redirections_replaces_the_list_of_that_client(): void
    {
        $this->freezeSecond();
        $nuevas = ['https://fuas.ubo.cl/cb', 'https://fuas-qa.ubo.cl/cb'];

        $this->clientes->cambiarRedirecciones(self::PUBLICO, $nuevas);

        $fila = DB::table('oauth_clients')->where('id', self::PUBLICO)->first(['redirect_uris', 'updated_at']);
        $this->assertSame($nuevas, json_decode($fila->redirect_uris, true));
        $this->assertStringStartsWith(now()->format('Y-m-d H:i:s'), $fila->updated_at);
        $this->assertSame($nuevas, $this->clientes->buscar(self::PUBLICO)?->redirecciones);
        $this->assertSame('[]', DB::table('oauth_clients')->where('id', self::OTRO)->value('redirect_uris'));
    }
}
