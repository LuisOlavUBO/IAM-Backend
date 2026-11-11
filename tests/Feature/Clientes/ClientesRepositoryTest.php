<?php

namespace Tests\Feature\Clientes;

use App\Domains\Clientes\Domain\Dto\ClienteParaEscribirDto;
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
            ['id' => self::CONFIDENCIAL, 'application_id' => $honorarios, 'name' => 'backend', 'secret' => 'hash-anterior', 'redirect_uris' => '[]', 'grant_types' => '[]', 'revoked' => false, 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => self::PUBLICO, 'application_id' => $fuas, 'name' => 'fuas-spa', 'secret' => null, 'redirect_uris' => '[]', 'grant_types' => '[]', 'revoked' => false, 'updated_at' => null],
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
            new ClienteParaEscribirDto(self::CONFIDENCIAL, 'backend', 'pago-honorarios', true, false),
            $this->clientes->buscar(strtoupper(self::CONFIDENCIAL)),
        );
        $this->assertEquals(new ClienteParaEscribirDto(self::PUBLICO, 'fuas-spa', 'fuas', false, false), $this->clientes->buscar(self::PUBLICO));
        $this->assertNull($this->clientes->buscar('0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4aff'));
    }

    public function test_it_finds_every_client_with_a_name_ordered_by_application(): void
    {
        $this->assertEquals([
            new ClienteParaEscribirDto(self::OTRO, 'backend', 'fuas', true, true),
            new ClienteParaEscribirDto(self::CONFIDENCIAL, 'backend', 'pago-honorarios', true, false),
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
}
